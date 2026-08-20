<?php

namespace Database\Seeders;

use App\Models\Rfa;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RfaSeeder extends Seeder
{
    public function run(): void
    {
        Rfa::query()->delete();

        $counter = 1;

        /*
        |--------------------------------------------------------------------------
        | 12 Pending RFAs
        |--------------------------------------------------------------------------
        */

        for ($i = 1; $i <= 12; $i++) {

            $dateFiled = Carbon::create(
                2026,
                8,
                1
            )->addDays(
                ($i - 1) % 12
            );

            Rfa::create([
                'reference_no' =>
                    sprintf(
                        'RFA-2026-%05d',
                        $counter++
                    ),

                'requesting_party' =>
                    "Requesting Party {$i}",

                'responding_party' =>
                    "Employer {$i}",

                'status' =>
                    'for_interviewer_assignment',

                'monitoring_bucket' =>
                    'pending',

                'mode_of_filing' =>
                    $i % 2 === 0
                        ? 'online'
                        : 'onsite',

                'date_filed' =>
                    $dateFiled,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 8 Ongoing RFAs
        |--------------------------------------------------------------------------
        */

        for ($i = 1; $i <= 8; $i++) {

            $dateFiled =
                Carbon::create(
                    2026,
                    8,
                    1
                )->addDays($i);

            $dateAssigned =
                $dateFiled
                    ->copy()
                    ->addDay();

            Rfa::create([
                'reference_no' =>
                    sprintf(
                        'RFA-2026-%05d',
                        $counter++
                    ),

                'requesting_party' =>
                    "Requesting Party {$counter}",

                'responding_party' =>
                    "Employer {$counter}",

                'status' =>
                    'for_validation',

                'monitoring_bucket' =>
                    'ongoing',

                'mode_of_filing' =>
                    $i % 2 === 0
                        ? 'online'
                        : 'onsite',

                'date_filed' =>
                    $dateFiled,

                'date_assigned_interviewer' =>
                    $dateAssigned,

                'date_interview' =>
                    $dateAssigned
                        ->copy()
                        ->addDays(2),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 10 Disposed RFAs
        |--------------------------------------------------------------------------
        */

        $dispositions = [
            'settled',
            'withdrawn',
            'referred',
            'lack_of_interest',
        ];

        for ($i = 1; $i <= 10; $i++) {

            $dateFiled =
                Carbon::create(
                    2026,
                    7,
                    10
                )->addDays($i);

            $dateAssigned =
                $dateFiled
                    ->copy()
                    ->addDay();

            $dateInterview =
                $dateAssigned
                    ->copy()
                    ->addDays(2);

            Rfa::create([
                'reference_no' =>
                    sprintf(
                        'RFA-2026-%05d',
                        $counter++
                    ),

                'requesting_party' =>
                    "Requesting Party {$counter}",

                'responding_party' =>
                    "Employer {$counter}",

                'status' =>
                    'disposed',

                'monitoring_bucket' =>
                    'disposed',

                'mode_of_filing' =>
                    $i % 2 === 0
                        ? 'online'
                        : 'onsite',

                'date_filed' =>
                    $dateFiled,

                'date_assigned_interviewer' =>
                    $dateAssigned,

                'date_interview' =>
                    $dateInterview,

                'date_validated' =>
                    $dateInterview,

                'date_turned_over_lr' =>
                    $dateInterview
                        ->copy()
                        ->addDay(),

                'date_assigned_seado' =>
                    $dateInterview
                        ->copy()
                        ->addDays(2),

                'date_disposed' =>
                    $dateFiled
                        ->copy()
                        ->addDays(10),

                'disposition_status' =>
                    $dispositions[
                        ($i - 1)
                        %
                        count($dispositions)
                    ],
            ]);
        }
    }
}