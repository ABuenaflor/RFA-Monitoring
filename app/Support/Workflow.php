<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The system's own workflow vocabulary.
 *
 * These are the values written to rfas.status by the CSV importer and by
 * case management. They are deliberately separate from source_case_status,
 * which is whatever the originating spreadsheet said.
 */
final class Workflow
{
    public const NEWLY_FILED = 'newly_filed';

    public const FOR_INTERVIEWER_ASSIGNMENT = 'for_interviewer_assignment';

    public const FOR_VALIDATION = 'for_validation';

    public const VALIDATED = 'validated';

    public const FOR_TURNOVER = 'for_turnover';

    public const FOR_SEADO_ASSIGNMENT = 'for_seado_assignment';

    public const ASSIGNED_TO_SEADO = 'assigned_to_seado';

    public const FOR_NOTICE_PREPARATION = 'for_notice_preparation';

    public const FOR_CONFERENCE = 'for_conference';

    public const ONGOING = 'ongoing';

    public const FOR_DISPOSITION = 'for_disposition';

    public const DISPOSED = 'disposed';


    /*
    |--------------------------------------------------------------------------
    | Monitoring Buckets
    |--------------------------------------------------------------------------
    |
    | The three dashboard-facing groupings.
    |
    */

    public const BUCKET_PENDING = 'pending';

    public const BUCKET_ONGOING = 'ongoing';

    public const BUCKET_DISPOSED = 'disposed';


    /**
     * Workflow statuses in processing order.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::NEWLY_FILED =>
                'Newly Filed',

            self::FOR_INTERVIEWER_ASSIGNMENT =>
                'For Interviewer Assignment',

            self::FOR_VALIDATION =>
                'For Validation',

            self::VALIDATED =>
                'Validated',

            self::FOR_TURNOVER =>
                'For Turnover to Labor Relations',

            self::FOR_SEADO_ASSIGNMENT =>
                'For SEADO Assignment',

            self::ASSIGNED_TO_SEADO =>
                'Assigned to SEADO',

            self::FOR_NOTICE_PREPARATION =>
                'For Notice Preparation',

            self::FOR_CONFERENCE =>
                'For Conference',

            self::ONGOING =>
                'Ongoing',

            self::FOR_DISPOSITION =>
                'For Disposition',

            self::DISPOSED =>
                'Disposed',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statusKeys(): array
    {
        return array_keys(self::statuses());
    }

    /**
     * Existing views render statuses with Str::headline, so an unrecognised
     * value coming from imported data still reads correctly.
     */
    public static function label(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        return self::statuses()[$status]
            ?? Str::headline($status);
    }

    /**
     * @return array<string, string>
     */
    public static function buckets(): array
    {
        return [
            self::BUCKET_PENDING => 'Pending',
            self::BUCKET_ONGOING => 'Ongoing',
            self::BUCKET_DISPOSED => 'Disposed',
        ];
    }

    public static function bucketLabel(?string $bucket): string
    {
        if ($bucket === null || $bucket === '') {
            return '—';
        }

        return self::buckets()[$bucket]
            ?? Str::headline($bucket);
    }

    /**
     * Position of a status in the processing order, or null when unknown.
     */
    public static function position(?string $status): ?int
    {
        $index = array_search(
            $status,
            self::statusKeys(),
            true
        );

        return $index === false
            ? null
            : $index;
    }

    public static function isDisposed(?string $status): bool
    {
        return $status === self::DISPOSED;
    }


    /*
    |--------------------------------------------------------------------------
    | Workflow Date Chain
    |--------------------------------------------------------------------------
    |
    | Ordered list of the workflow date fields with their labels. Used for
    | chronology validation and for rendering the case timeline.
    |
    */

    /**
     * @return array<string, string>
     */
    public static function dateChain(): array
    {
        return [
            'date_filed' =>
                'Date Filed',

            'date_assigned_interviewer' =>
                'Date Assigned to Interviewer',

            'date_interview' =>
                'Date of Interview',

            'date_validated' =>
                'Date Validated',

            'date_turned_over_lr' =>
                'Date Turned Over to LR',

            'date_assigned_seado' =>
                'Date Assigned to SEADO',

            'date_initial_conference' =>
                'Date of 1st Conference',

            'date_second_conference' =>
                'Date of 2nd Conference',

            'date_disposed' =>
                'Date Disposed',
        ];
    }

    public static function dateLabel(string $field): string
    {
        return self::dateChain()[$field]
            ?? Str::headline($field);
    }
}
