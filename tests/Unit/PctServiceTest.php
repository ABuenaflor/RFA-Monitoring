<?php

namespace Tests\Unit;

use App\Models\Rfa;
use App\Services\PctCalendar;
use App\Services\PctService;
use Carbon\Carbon;
use Tests\TestCase;

class PctServiceTest extends TestCase
{
    /**
     * The rule tests below were written in calendar days; the working-day
     * tests at the end swap in their own calendar.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->useCalendar(PctCalendar::MODE_CALENDAR);
    }

    /**
     * @param  array<string, string>  $holidays
     */
    private function useCalendar(string $mode, array $holidays = []): void
    {
        $this->app->instance(PctCalendar::class, new PctCalendar($mode, $holidays));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function checkpoint(string $key, array $attributes, ?string $asOf = null): array
    {
        return app(PctService::class)->evaluate(
            new Rfa($attributes),
            $asOf ? Carbon::parse($asOf) : null
        )['checkpoints'][$key];
    }

    private function assertRated(array $checkpoint, string $state, int $days, string $classification): void
    {
        $this->assertSame($state, $checkpoint['state']);
        $this->assertSame($days, $checkpoint['days']);
        $this->assertSame($classification, $checkpoint['classification_key']);
    }

    /*
    |--------------------------------------------------------------------------
    | The five rules
    |--------------------------------------------------------------------------
    */

    public function test_there_are_five_named_checkpoints_in_workflow_order(): void
    {
        $this->assertSame(
            [
                'Date Filed - Interviewer Assignment',
                'Interviewer Assignment - Date Interviewed',
                'Date Interviewed - SEADO Assignment',
                'SEADO Assignment - 1st Conference',
                '1st Conference - Date Disposed',
            ],
            array_column(PctService::definitions(), 'label')
        );

        $checkpoints = app(PctService::class)
            ->evaluate(new Rfa(['date_filed' => '2026-01-01']))['checkpoints'];

        $this->assertSame(PctService::keys(), array_keys($checkpoints));
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Date Filed - Interviewer Assignment
    |--------------------------------------------------------------------------
    */

    public function test_onsite_assigned_the_same_day_is_nearing_and_compliant(): void
    {
        $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
            'mode_of_filing' => 'onsite',
            'date_filed' => '2026-01-05',
            'date_assigned_interviewer' => '2026-01-05',
        ]);

        $this->assertRated($result, 'completed', 0, 'nearing');
        $this->assertTrue($result['is_compliant']);
        $this->assertSame(1, $result['limit_days']);
    }

    public function test_onsite_assigned_the_next_day_is_on_pct_and_compliant(): void
    {
        $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
            'mode_of_filing' => 'onsite',
            'date_filed' => '2026-01-05',
            'date_assigned_interviewer' => '2026-01-06',
        ]);

        $this->assertRated($result, 'completed', 1, 'on');
        $this->assertTrue($result['is_compliant']);
    }

    public function test_onsite_assigned_two_days_later_is_beyond_pct(): void
    {
        $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
            'mode_of_filing' => 'onsite',
            'date_filed' => '2026-01-05',
            'date_assigned_interviewer' => '2026-01-07',
        ]);

        $this->assertRated($result, 'completed', 2, 'beyond');
        $this->assertFalse($result['is_compliant']);
        $this->assertSame(1, $result['overdue_days']);
    }

    public function test_an_unassigned_onsite_case_is_due_the_day_after_filing(): void
    {
        $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
            'mode_of_filing' => 'onsite',
            'date_filed' => '2026-01-05',
        ], '2026-01-05');

        $this->assertRated($result, 'active', 0, 'nearing');
        $this->assertSame(1, $result['remaining_days']);
    }

    public function test_online_filing_has_a_two_day_limit(): void
    {
        foreach ([
            '2026-01-05' => [0, 'within'],
            '2026-01-06' => [1, 'nearing'],
            '2026-01-07' => [2, 'on'],
            '2026-01-08' => [3, 'beyond'],
        ] as $assigned => [$days, $classification]) {
            $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
                'mode_of_filing' => 'online',
                'date_filed' => '2026-01-05',
                'date_assigned_interviewer' => $assigned,
            ]);

            $this->assertRated($result, 'completed', $days, $classification);
        }
    }

    public function test_mode_of_filing_spellings_are_normalised(): void
    {
        foreach (['On-site', 'ONSITE', 'on site', 'Walk-in'] as $mode) {
            $this->assertSame(PctService::MODE_ONSITE, PctService::normalizeMode($mode));
        }

        foreach (['Online', 'on-line', ' ONLINE '] as $mode) {
            $this->assertSame(PctService::MODE_ONLINE, PctService::normalizeMode($mode));
        }

        $this->assertNull(PctService::normalizeMode(''));
        $this->assertNull(PctService::normalizeMode('email'));
    }

    public function test_a_blank_mode_of_filing_cannot_be_rated(): void
    {
        $result = $this->checkpoint(PctService::FILING_ASSIGNMENT, [
            'mode_of_filing' => '',
            'date_filed' => '2026-01-05',
            'date_assigned_interviewer' => '2026-01-20',
        ]);

        $this->assertSame('missing_mode', $result['state']);
        $this->assertNull($result['classification_key']);
        $this->assertNull($result['is_compliant']);
        $this->assertSame('Mode of Filing Missing', PctService::statusLabel($result));
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Interviewer Assignment - Date Interviewed (3 days)
    |--------------------------------------------------------------------------
    */

    public function test_assignment_to_interview_has_a_three_day_limit(): void
    {
        foreach ([
            '2026-01-02' => [1, 'within'],
            '2026-01-03' => [2, 'nearing'],
            '2026-01-04' => [3, 'on'],
            '2026-01-05' => [4, 'beyond'],
        ] as $interviewed => [$days, $classification]) {
            $result = $this->checkpoint(PctService::ASSIGNMENT_INTERVIEW, [
                'date_assigned_interviewer' => '2026-01-01',
                'date_interview' => $interviewed,
            ]);

            $this->assertRated($result, 'completed', $days, $classification);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Date Interviewed - SEADO Assignment (3 days)
    |--------------------------------------------------------------------------
    */

    public function test_interview_to_seado_assignment_has_a_three_day_limit(): void
    {
        $result = $this->checkpoint(PctService::INTERVIEW_SEADO, [
            'date_interview' => '2026-01-01',
            'date_assigned_seado' => '2026-01-04',
        ]);

        $this->assertRated($result, 'completed', 3, 'on');
        $this->assertTrue($result['is_compliant']);

        $late = $this->checkpoint(PctService::INTERVIEW_SEADO, [
            'date_interview' => '2026-01-01',
            'date_assigned_seado' => '2026-01-05',
        ]);

        $this->assertRated($late, 'completed', 4, 'beyond');
    }

    public function test_interview_to_seado_needs_an_interview_date(): void
    {
        $result = $this->checkpoint(PctService::INTERVIEW_SEADO, [
            'date_assigned_seado' => '2026-01-04',
        ]);

        $this->assertSame('missing_start', $result['state']);
        $this->assertSame('Date Interviewed is missing.', $result['message']);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. SEADO Assignment - 1st Conference (10 days)
    |--------------------------------------------------------------------------
    */

    public function test_seado_to_first_conference_warns_over_the_last_three_days(): void
    {
        foreach ([
            '2026-01-07' => [6, 'within'],
            '2026-01-08' => [7, 'nearing'],
            '2026-01-10' => [9, 'nearing'],
            '2026-01-11' => [10, 'on'],
            '2026-01-12' => [11, 'beyond'],
        ] as $conference => [$days, $classification]) {
            $result = $this->checkpoint(PctService::SEADO_CONFERENCE, [
                'date_assigned_seado' => '2026-01-01',
                'date_initial_conference' => $conference,
            ]);

            $this->assertRated($result, 'completed', $days, $classification);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. 1st Conference - Date Disposed (30 days)
    |--------------------------------------------------------------------------
    */

    public function test_first_conference_to_disposal_has_a_thirty_day_limit(): void
    {
        foreach ([
            '2026-01-27' => [26, 'within'],
            '2026-01-28' => [27, 'nearing'],
            '2026-01-31' => [30, 'on'],
            '2026-02-01' => [31, 'beyond'],
        ] as $disposed => [$days, $classification]) {
            $result = $this->checkpoint(PctService::CONFERENCE_DISPOSED, [
                'date_initial_conference' => '2026-01-01',
                'date_disposed' => $disposed,
                'monitoring_bucket' => 'disposed',
                'status' => 'disposed',
            ]);

            $this->assertRated($result, 'completed', $days, $classification);
        }
    }

    public function test_the_thirty_day_clock_starts_at_the_first_conference_not_filing(): void
    {
        $result = $this->checkpoint(PctService::CONFERENCE_DISPOSED, [
            'date_filed' => '2025-10-01',
            'date_initial_conference' => '2026-01-01',
            'date_disposed' => '2026-01-11',
            'monitoring_bucket' => 'disposed',
        ]);

        $this->assertRated($result, 'completed', 10, 'within');
    }

    public function test_an_active_disposition_clock_counts_to_today(): void
    {
        $result = $this->checkpoint(PctService::CONFERENCE_DISPOSED, [
            'date_initial_conference' => '2026-01-01',
        ], '2026-02-05');

        $this->assertRated($result, 'active', 35, 'beyond');
        $this->assertSame(5, $result['overdue_days']);
        $this->assertSame('2026-01-31', $result['deadline']->toDateString());
    }

    public function test_a_case_marked_disposed_without_a_date_is_not_aged(): void
    {
        $result = $this->checkpoint(PctService::CONFERENCE_DISPOSED, [
            'date_initial_conference' => '2026-01-01',
            'monitoring_bucket' => 'disposed',
            'status' => 'disposed',
        ], '2026-06-01');

        $this->assertSame('missing_end', $result['state']);
        $this->assertNull($result['days']);
    }

    /*
    |--------------------------------------------------------------------------
    | Shared behaviour
    |--------------------------------------------------------------------------
    */

    public function test_an_active_checkpoint_uses_the_current_date(): void
    {
        Carbon::setTestNow('2026-01-04 15:00:00');

        $result = $this->checkpoint(PctService::ASSIGNMENT_INTERVIEW, [
            'date_assigned_interviewer' => '2026-01-01',
        ]);

        $this->assertRated($result, 'active', 3, 'on');
        $this->assertSame(0, $result['remaining_days']);
    }

    public function test_a_passed_checkpoint_does_not_keep_aging_without_its_end_date(): void
    {
        $attributes = [
            'mode_of_filing' => 'online',
            'date_filed' => '2026-01-01',
            'date_assigned_interviewer' => '2026-01-02',
            'date_assigned_seado' => '2026-01-10',
        ];

        $interview = $this->checkpoint(
            PctService::ASSIGNMENT_INTERVIEW,
            $attributes,
            '2026-03-01'
        );

        $this->assertSame('missing_end', $interview['state']);
        $this->assertFalse($interview['is_active']);

        $seado = $this->checkpoint(
            PctService::SEADO_CONFERENCE,
            $attributes,
            '2026-01-15'
        );

        $this->assertRated($seado, 'active', 5, 'within');
    }

    public function test_a_later_workflow_status_also_counts_as_progress(): void
    {
        $result = $this->checkpoint(PctService::INTERVIEW_SEADO, [
            'date_interview' => '2026-01-01',
            'status' => 'assigned_to_seado',
        ], '2026-02-01');

        $this->assertSame('missing_end', $result['state']);
    }

    public function test_an_end_date_before_the_start_date_is_invalid(): void
    {
        $result = $this->checkpoint(PctService::SEADO_CONFERENCE, [
            'date_assigned_seado' => '2026-01-10',
            'date_initial_conference' => '2026-01-05',
        ]);

        $this->assertSame('invalid', $result['state']);
        $this->assertSame('Invalid Dates', PctService::statusLabel($result));
    }

    public function test_total_processing_days_are_not_rated(): void
    {
        $result = app(PctService::class)->evaluate(new Rfa([
            'date_filed' => '2026-01-01',
            'date_disposed' => '2026-03-02',
        ]));

        $this->assertSame('completed', $result['total_processing']['state']);
        $this->assertSame(60, $result['total_processing']['days']);
        $this->assertArrayNotHasKey('classification_key', $result['total_processing']);
    }
    /*
    |--------------------------------------------------------------------------
    | Working days and holidays
    |--------------------------------------------------------------------------
    */

    public function test_working_days_skip_the_weekend(): void
    {
        $this->useCalendar(PctCalendar::MODE_WORKING);

        // Thu 2026-01-08 → Tue 2026-01-13: Fri, Mon, Tue = 3 working days.
        $result = $this->checkpoint(PctService::ASSIGNMENT_INTERVIEW, [
            'date_assigned_interviewer' => '2026-01-08',
            'date_interview' => '2026-01-13',
        ]);

        $this->assertRated($result, 'completed', 3, 'on');
        $this->assertSame('2026-01-13', $result['deadline']->format('Y-m-d'));
    }

    public function test_working_days_skip_holidays_and_events(): void
    {
        $this->useCalendar(PctCalendar::MODE_WORKING, [
            '2026-01-12' => 'Special non-working day',
        ]);

        // Thu 2026-01-08 → Tue 2026-01-13 with Monday off: Fri, Tue = 2.
        $result = $this->checkpoint(PctService::ASSIGNMENT_INTERVIEW, [
            'date_assigned_interviewer' => '2026-01-08',
            'date_interview' => '2026-01-13',
        ]);

        $this->assertRated($result, 'completed', 2, 'nearing');
        $this->assertSame('2026-01-14', $result['deadline']->format('Y-m-d'));
    }

    public function test_a_holiday_on_a_weekend_changes_nothing(): void
    {
        $calendar = new PctCalendar(PctCalendar::MODE_WORKING, [
            '2026-01-10' => 'Saturday event',
        ]);

        $this->assertSame(
            5,
            $calendar->daysBetween(Carbon::parse('2026-01-05'), Carbon::parse('2026-01-12'))
        );

        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-01-10')));
        $this->assertSame('Saturday event', $calendar->holidayName(Carbon::parse('2026-01-10')));
    }

    public function test_an_active_clock_does_not_age_over_the_weekend(): void
    {
        $this->useCalendar(PctCalendar::MODE_WORKING);

        // Started Fri 2026-01-09, checked on Sun 2026-01-11: still day 0.
        $result = $this->checkpoint(PctService::ASSIGNMENT_INTERVIEW, [
            'date_assigned_interviewer' => '2026-01-09',
        ], '2026-01-11');

        $this->assertRated($result, 'active', 0, 'within');
    }

    public function test_working_day_end_before_start_is_still_invalid(): void
    {
        $this->useCalendar(PctCalendar::MODE_WORKING);

        // Sunday back to Saturday: no working day between, still invalid.
        $result = $this->checkpoint(PctService::SEADO_CONFERENCE, [
            'date_assigned_seado' => '2026-01-11',
            'date_initial_conference' => '2026-01-10',
        ]);

        $this->assertSame('invalid', $result['state']);
    }

    public function test_long_spans_match_a_day_by_day_count(): void
    {
        $holidays = ['2026-02-25' => 'A', '2026-04-09' => 'B', '2026-05-01' => 'C'];

        $calendar = new PctCalendar(PctCalendar::MODE_WORKING, $holidays);

        $start = Carbon::parse('2026-01-01');

        $expected = 0;

        for ($date = $start->copy()->addDay(); $date->lte('2026-06-30'); $date->addDay()) {
            if (! $date->isWeekend() && ! isset($holidays[$date->format('Y-m-d')])) {
                $expected++;
            }

            $this->assertSame($expected, $calendar->daysBetween($start, $date), $date->format('Y-m-d'));
        }
    }
}
