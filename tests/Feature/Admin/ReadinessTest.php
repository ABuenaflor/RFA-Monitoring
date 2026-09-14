<?php

namespace Tests\Feature\Admin;

use App\Models\ReleaseSignoff;
use App\Models\UatResult;
use App\Models\User;
use App\Services\ReadinessService;
use App\Support\UatPlan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()
            ->administrator()
            ->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Checks
    |--------------------------------------------------------------------------
    */

    public function test_every_check_reports_a_recognised_status(): void
    {
        $checks = app(ReadinessService::class)->checks();

        $this->assertNotEmpty($checks);

        foreach ($checks as $check) {
            $this->assertContains(
                $check['status'],
                [
                    ReadinessService::PASS,
                    ReadinessService::WARN,
                    ReadinessService::FAIL,
                ],
                'Unexpected status on check ' . $check['key']
            );

            $this->assertNotEmpty($check['detail']);

            $this->assertNotEmpty($check['recommendation']);
        }
    }

    public function test_the_summary_adds_up_to_the_number_of_checks(): void
    {
        $service = app(ReadinessService::class);

        $summary = $service->summary();

        $this->assertSame(
            $summary['total'],
            $summary['passing']
                + $summary['warnings']
                + $summary['failing']
        );
    }

    public function test_a_single_administrator_is_reported_as_a_risk(): void
    {
        $checks = collect(
            app(ReadinessService::class)->checks()
        )->keyBy('key');

        $this->assertSame(
            ReadinessService::WARN,
            $checks['security.administrators']['status']
        );

        User::factory()->administrator()->create();

        $checks = collect(
            app(ReadinessService::class)->checks()
        )->keyBy('key');

        $this->assertSame(
            ReadinessService::PASS,
            $checks['security.administrators']['status']
        );
    }

    public function test_never_having_taken_a_backup_fails_the_check(): void
    {
        $checks = collect(
            app(ReadinessService::class)->checks()
        )->keyBy('key');

        $this->assertSame(
            ReadinessService::FAIL,
            $checks['data.backup_recent']['status']
        );
    }

    public function test_an_application_key_is_present(): void
    {
        $checks = collect(
            app(ReadinessService::class)->checks()
        )->keyBy('key');

        $this->assertSame(
            ReadinessService::PASS,
            $checks['env.app_key']['status']
        );
    }

    public function test_the_environment_file_is_not_tracked(): void
    {
        $checks = collect(
            app(ReadinessService::class)->checks()
        )->keyBy('key');

        $this->assertSame(
            ReadinessService::PASS,
            $checks['deploy.env_untracked']['status']
        );
    }

    public function test_the_page_renders_for_a_permitted_user(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.readiness.index'))
            ->assertOk()
            ->assertSee('Release Sign-off')
            ->assertSee('User Acceptance Testing');
    }

    public function test_the_page_is_closed_without_the_permission(): void
    {
        $seado = User::factory()
            ->withRole('seado')
            ->create();

        $this->actingAs($seado)
            ->get(route('admin.readiness.index'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | UAT
    |--------------------------------------------------------------------------
    */

    public function test_the_plan_has_no_duplicate_case_keys(): void
    {
        $keys = UatPlan::caseKeys();

        $this->assertSame(
            count($keys),
            count(array_unique($keys))
        );
    }

    public function test_a_result_can_be_recorded(): void
    {
        $key = UatPlan::caseKeys()[0];

        $this->actingAs($this->admin)
            ->post(route('admin.readiness.results'), [
                'case_key' => $key,
                'status' => UatPlan::STATUS_PASSED,
                'notes' => 'Verified on the staging copy.',
            ])
            ->assertRedirect();

        $result = UatResult::query()
            ->where('case_key', $key)
            ->first();

        $this->assertNotNull($result);

        $this->assertSame(UatPlan::STATUS_PASSED, $result->status);

        $this->assertSame($this->admin->id, $result->tested_by);

        $this->assertNotNull($result->tested_at);
    }

    public function test_recording_a_result_twice_updates_rather_than_duplicates(): void
    {
        $key = UatPlan::caseKeys()[0];

        foreach ([UatPlan::STATUS_FAILED, UatPlan::STATUS_PASSED] as $status) {
            $this->actingAs($this->admin)
                ->post(route('admin.readiness.results'), [
                    'case_key' => $key,
                    'status' => $status,
                ]);
        }

        $this->assertSame(
            1,
            UatResult::query()->where('case_key', $key)->count()
        );

        $this->assertSame(
            UatPlan::STATUS_PASSED,
            UatResult::query()->where('case_key', $key)->first()->status
        );
    }

    public function test_an_unknown_case_key_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.readiness.results'), [
                'case_key' => 'not.a.real.case',
                'status' => UatPlan::STATUS_PASSED,
            ])
            ->assertSessionHasErrors('case_key');

        $this->assertSame(0, UatResult::query()->count());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.readiness.results'), [
                'case_key' => UatPlan::caseKeys()[0],
                'status' => 'probably_fine',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_recording_results_requires_the_manage_permission(): void
    {
        $role = \App\Models\Role::create([
            'name' => 'Observer',
            'slug' => 'observer',
            'is_system' => false,
        ]);

        $role->syncPermissions([
            \App\Support\Permissions::READINESS_VIEW,
        ]);

        $observer = User::factory()
            ->withRole($role)
            ->create();

        $this->actingAs($observer)
            ->get(route('admin.readiness.index'))
            ->assertOk();

        $this->actingAs($observer)
            ->post(route('admin.readiness.results'), [
                'case_key' => UatPlan::caseKeys()[0],
                'status' => UatPlan::STATUS_PASSED,
            ])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Sign-off
    |--------------------------------------------------------------------------
    */

    public function test_a_signoff_freezes_the_readiness_picture(): void
    {
        UatResult::create([
            'case_key' => UatPlan::caseKeys()[0],
            'status' => UatPlan::STATUS_PASSED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.readiness.signoff'), [
                'version' => '1.0.0',
                'environment' => 'production',
                'summary' => 'Accepted with outstanding HTTPS work.',
            ])
            ->assertRedirect();

        $signoff = ReleaseSignoff::query()->firstOrFail();

        $this->assertSame('1.0.0', $signoff->version);

        $this->assertSame(1, $signoff->cases_passed);

        $this->assertSame(
            UatPlan::totalCases(),
            $signoff->cases_total
        );

        $this->assertSame($this->admin->id, $signoff->signed_by);

        $this->assertNotNull($signoff->signed_at);
    }

    public function test_a_signoff_requires_a_version_and_environment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.readiness.signoff'), [])
            ->assertSessionHasErrors(['version', 'environment']);

        $this->assertSame(0, ReleaseSignoff::query()->count());
    }
}
