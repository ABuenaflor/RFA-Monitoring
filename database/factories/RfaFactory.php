<?php

namespace Database\Factories;

use App\Models\Rfa;
use App\Support\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfa>
 */
class RfaFactory extends Factory
{
    protected $model = Rfa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' =>
                'RFA-TEST-' . fake()->unique()->numerify('######'),

            'office' => 'PFO Albay',

            'docket_no' =>
                'RFA-' . fake()->numerify('####') . '-OL',

            'requesting_party' => fake()->name(),

            'responding_party' => fake()->company(),

            'status' => Workflow::FOR_INTERVIEWER_ASSIGNMENT,

            'monitoring_bucket' => Workflow::BUCKET_PENDING,

            'date_filed' => '2026-08-03',
        ];
    }

    /**
     * A case that has moved into interviewer processing.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Workflow::FOR_VALIDATION,

            'monitoring_bucket' => Workflow::BUCKET_ONGOING,

            'date_assigned_interviewer' => '2026-08-05',

            'date_interview' => '2026-08-07',
        ]);
    }

    /**
     * A closed case with a complete disposition.
     */
    public function disposed(): static
    {
        return $this->ongoing()->state(fn (array $attributes) => [
            'status' => Workflow::DISPOSED,

            'monitoring_bucket' => Workflow::BUCKET_DISPOSED,

            'date_initial_conference' => '2026-08-12',

            'disposition_status' => 'settled',

            'disposition_mode' => 'SC',

            'date_disposed' => '2026-08-20',
        ]);
    }
}
