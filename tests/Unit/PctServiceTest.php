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

    public function test_same_day_disposal_is_within_30_day_pct(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'date_disposed' =>
            '2026-01-01',

        'monitoring_bucket' =>
            'disposed',

        'status' =>
            'disposed',
    ]);

    $result =
        app(PctService::class)
            ->evaluate($rfa);

    $pct =
        $result[
            'disposition_pct'
        ];

    $this->assertSame(
        'completed',
        $pct['state']
    );

    $this->assertSame(
        0,
        $pct['days']
    );

    $this->assertSame(
        'disposed_within',
        $pct['status_key']
    );

    $this->assertTrue(
        $pct['is_compliant']
    );
}
public function test_29_day_disposal_is_within_pct(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'date_disposed' =>
            '2026-01-30',

        'monitoring_bucket' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate($rfa)[
            'disposition_pct'
        ];

    $this->assertSame(
        29,
        $pct['days']
    );

    $this->assertSame(
        'disposed_within',
        $pct['status_key']
    );
}

public function test_day_30_is_still_disposed_within_pct(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'date_disposed' =>
            '2026-01-31',

        'monitoring_bucket' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate($rfa)[
            'disposition_pct'
        ];

    $this->assertSame(
        30,
        $pct['days']
    );

    $this->assertSame(
        'disposed_within',
        $pct['status_key']
    );

    $this->assertTrue(
        $pct['is_compliant']
    );
}

public function test_day_31_is_disposed_beyond_pct(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'date_disposed' =>
            '2026-02-01',

        'monitoring_bucket' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate($rfa)[
            'disposition_pct'
        ];

    $this->assertSame(
        31,
        $pct['days']
    );

    $this->assertSame(
        'disposed_beyond',
        $pct['status_key']
    );

    $this->assertFalse(
        $pct['is_compliant']
    );

    $this->assertSame(
        1,
        $pct['overdue_days']
    );
}

public function test_active_day_29_is_within_disposition_window(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'monitoring_bucket' =>
            'ongoing',
    ]);

    $pct = app(PctService::class)
        ->evaluate(
            $rfa,
            Carbon::parse(
                '2026-01-30'
            )
        )[
            'disposition_pct'
        ];

    $this->assertSame(
        'active',
        $pct['state']
    );

    $this->assertSame(
        29,
        $pct['days']
    );

    $this->assertSame(
        'active_within',
        $pct['status_key']
    );

    $this->assertSame(
        1,
        $pct['remaining_days']
    );
}

public function test_active_day_30_is_due_today(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'monitoring_bucket' =>
            'ongoing',
    ]);

    $pct = app(PctService::class)
        ->evaluate(
            $rfa,
            Carbon::parse(
                '2026-01-31'
            )
        )[
            'disposition_pct'
        ];

    $this->assertSame(
        30,
        $pct['days']
    );

    $this->assertSame(
        'due_today',
        $pct['status_key']
    );

    $this->assertSame(
        0,
        $pct['remaining_days']
    );

    $this->assertSame(
        0,
        $pct['overdue_days']
    );
}

public function test_active_day_31_is_beyond_disposition_pct(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'monitoring_bucket' =>
            'ongoing',
    ]);

    $pct = app(PctService::class)
        ->evaluate(
            $rfa,
            Carbon::parse(
                '2026-02-01'
            )
        )[
            'disposition_pct'
        ];

    $this->assertSame(
        31,
        $pct['days']
    );

    $this->assertSame(
        'active_beyond',
        $pct['status_key']
    );

    $this->assertSame(
        1,
        $pct['overdue_days']
    );
}

public function test_missing_date_filed_makes_disposition_pct_indeterminate(): void
{
    $rfa = new Rfa([
        'date_disposed' =>
            '2026-01-20',

        'monitoring_bucket' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate($rfa)[
            'disposition_pct'
        ];

    $this->assertSame(
        'missing_start',
        $pct['state']
    );

    $this->assertSame(
        'indeterminate',
        $pct['status_key']
    );
}

public function test_disposed_case_without_date_disposed_is_indeterminate(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'monitoring_bucket' =>
            'disposed',

        'status' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate(
            $rfa,
            Carbon::parse(
                '2026-04-01'
            )
        )[
            'disposition_pct'
        ];

    $this->assertSame(
        'missing_end',
        $pct['state']
    );

    $this->assertFalse(
        $pct['is_active']
    );

    $this->assertSame(
        'indeterminate',
        $pct['status_key']
    );
}

public function test_completed_disposition_pct_does_not_continue_aging(): void
{
    $rfa = new Rfa([
        'date_filed' =>
            '2026-01-01',

        'date_disposed' =>
            '2026-01-31',

        'monitoring_bucket' =>
            'disposed',
    ]);

    $pct = app(PctService::class)
        ->evaluate(
            $rfa,
            Carbon::parse(
                '2026-06-01'
            )
        )[
            'disposition_pct'
        ];

    $this->assertSame(
        30,
        $pct['days']
    );

    $this->assertSame(
        'disposed_within',
        $pct['status_key']
    );

    $this->assertTrue(
        $pct['is_completed']
    );
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
