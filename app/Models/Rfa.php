<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rfa extends Model
{
    protected $fillable = [
        'reference_no',
        'requesting_party',
        'responding_party',
        'status',
        'monitoring_bucket',
        'mode_of_filing',

        'date_filed',

        'interviewer_id',
        'date_assigned_interviewer',
        'date_interview',
        'date_validated',

        'date_turned_over_lr',

        'seado_id',
        'date_assigned_seado',

        'disposition_status',
        'date_disposed',

        'import_batch_uuid',
        'import_payload',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',

            'date_assigned_interviewer' => 'date',
            'date_interview' => 'date',
            'date_validated' => 'date',

            'date_turned_over_lr' => 'date',

            'date_assigned_seado' => 'date',

            'date_disposed' => 'date',

            'import_payload' => 'array',
        ];
    }
}
