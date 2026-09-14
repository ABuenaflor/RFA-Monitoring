<?php

namespace App\Models;

use App\Support\Workflow;
use Database\Factories\RfaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfa extends Model
{
    /** @use HasFactory<RfaFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_no',

        'office',
        'docket_no',

        'requesting_party',
        'responding_party',
        'company_address',
        'contact_no',

        'total_employment',
        'industry',
        'industry_code',
        'size_of_enterprise',

        'status',
        'source_case_status',
        'monitoring_bucket',
        'mode_of_filing',

        'date_filed',
        'date_ta_nores',

        'interviewer_id',
        'interviewer_name',
        'date_assigned_interviewer',
        'date_interview',
        'date_validated',

        'date_turned_over_lr',

        'seado_id',
        'seado_name',
        'date_assigned_seado',

        'date_initial_conference',
        'date_second_conference',
        'date_both_parties_appeared',

        'workers_involved',
        'male_workers',
        'female_workers',
        'workers_benefited',

        'filer_class',
        'issues',

        'disposition_status',
        'disposition_mode',
        'date_disposed',

        'monetary_benefit',

        'source_row_key',
        'import_batch_uuid',
        'import_payload',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',
            'date_ta_nores' => 'date',

            'date_assigned_interviewer' => 'date',
            'date_interview' => 'date',
            'date_validated' => 'date',

            'date_turned_over_lr' => 'date',
            'date_assigned_seado' => 'date',

            'date_initial_conference' => 'date',
            'date_second_conference' => 'date',
            'date_both_parties_appeared' => 'date',

            'date_disposed' => 'date',

            'total_employment' => 'integer',
            'workers_involved' => 'integer',
            'male_workers' => 'integer',
            'female_workers' => 'integer',
            'workers_benefited' => 'integer',

            'monetary_benefit' => 'decimal:2',

            'import_payload' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
    | interviewer_id / seado_id link to system users where the case has been
    | assigned inside the application. Records imported from CSV keep only
    | the free-text name, so both sides are always kept in step.
    |
    */

    public function activities(): HasMany
    {
        return $this->hasMany(RfaActivity::class)
            ->latest('created_at')
            ->latest('id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'interviewer_id'
        );
    }

    public function seado(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'seado_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation Helpers
    |--------------------------------------------------------------------------
    */

    public function statusLabel(): string
    {
        return Workflow::label($this->status);
    }

    public function bucketLabel(): string
    {
        return Workflow::bucketLabel($this->monitoring_bucket);
    }

    /**
     * Docket number when one exists, otherwise the internal reference.
     */
    public function displayReference(): string
    {
        return $this->docket_no
            ?: (string) $this->reference_no;
    }

    public function isDisposed(): bool
    {
        return $this->monitoring_bucket === Workflow::BUCKET_DISPOSED
            || $this->status === Workflow::DISPOSED
            || $this->date_disposed !== null;
    }
}
