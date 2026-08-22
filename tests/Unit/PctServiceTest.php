<?php

namespace Tests\Unit;

use App\Models\Rfa;
use App\Services\PctService;
use Carbon\Carbon;
use Tests\TestCase;

class PctServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_one_day_is_within_pct(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-08-01',

            'date_assigned_interviewer' =>
                '2026-08-02',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-10'
                )
            );

        $this->assertSame(
            'completed',
            $result[
                'stage_one'
            ]['state']
        );

        $this->assertSame(
            1,
            $result[
                'stage_one'
            ]['days']
        );

        $this->assertSame(
            'within',
            $result[
                'stage_one'
            ][
                'classification_key'
            ]
        );
    }

    public function test_two_days_is_nearing_pct(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-08-01',

            'date_assigned_interviewer' =>
                '2026-08-03',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-10'
                )
            );

        $this->assertSame(
            'nearing',
            $result[
                'stage_one'
            ][
                'classification_key'
            ]
        );
    }

    public function test_three_days_is_on_pct(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-08-01',

            'date_assigned_interviewer' =>
                '2026-08-04',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-10'
                )
            );

        $this->assertSame(
            'on',
            $result[
                'stage_one'
            ][
                'classification_key'
            ]
        );
    }

    public function test_more_than_three_days_is_beyond_pct(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-08-01',

            'date_assigned_interviewer' =>
                '2026-08-05',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-10'
                )
            );

        $this->assertSame(
            'beyond',
            $result[
                'stage_one'
            ][
                'classification_key'
            ]
        );
    }

    public function test_active_stage_uses_current_date(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-08-20',

            'status' =>
                'for_interviewer_assignment',

            'monitoring_bucket' =>
                'pending',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-22'
                )
            );

        $this->assertSame(
            'active',
            $result[
                'stage_one'
            ]['state']
        );

        $this->assertSame(
            2,
            $result[
                'stage_one'
            ]['days']
        );

        $this->assertSame(
            'nearing',
            $result[
                'stage_one'
            ][
                'classification_key'
            ]
        );
    }

    public function test_missing_interview_date_does_not_keep_aging_after_later_event(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-06-01',

            'date_assigned_interviewer' =>
                '2026-06-02',

            'date_assigned_seado' =>
                '2026-06-07',

            'date_initial_conference' =>
                '2026-06-10',

            'status' =>
                'for_conference',

            'monitoring_bucket' =>
                'ongoing',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-22'
                )
            );

        $this->assertSame(
            'missing_end',
            $result[
                'stage_two'
            ]['state']
        );

        $this->assertFalse(
            $result[
                'stage_two'
            ]['is_active']
        );
    }

    public function test_total_processing_days_are_separate_from_pct(): void
    {
        $service = new PctService();

        $rfa = new Rfa([
            'date_filed' =>
                '2026-07-01',

            'date_disposed' =>
                '2026-07-10',

            'status' =>
                'disposed',

            'monitoring_bucket' =>
                'disposed',
        ]);

        $result =
            $service->evaluate(
                $rfa,
                Carbon::parse(
                    '2026-08-22'
                )
            );

        $this->assertSame(
            'completed',
            $result[
                'total_processing'
            ]['state']
        );

        $this->assertSame(
            9,
            $result[
                'total_processing'
            ]['days']
        );
    }
}
