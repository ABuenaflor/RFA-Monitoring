<?php

namespace Tests\Feature\Admin;

use App\Models\DatabaseBackup;
use App\Models\ImportBatch;
use App\Models\Rfa;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DataQualityService;
use App\Services\SettingsService;
use App\Support\SystemSettings;
use App\Support\Workflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GovernanceTest extends TestCase
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
    | Data Quality
    |--------------------------------------------------------------------------
    */

    public function test_a_clean_record_trips_no_critical_check(): void
    {
        Rfa::factory()->create([
            'requesting_party' => 'A Requester',
            'responding_party' => 'An Employer',
        ]);

        $summary = app(DataQualityService::class)->summary();

        $this->assertSame(0, $summary['critical']);
    }

    public function test_a_record_disposed_without_a_date_is_flagged(): void
    {
        Rfa::factory()->create([
            'monitoring_bucket' => Workflow::BUCKET_DISPOSED,
            'status' => Workflow::DISPOSED,
            'date_disposed' => null,
        ]);

        $findings = collect(
            app(DataQualityService::class)->findings()
        )->keyBy('key');

        $this->assertSame(
            1,
            $findings['disposed_without_date']['count']
        );
    }

    public function test_a_missing_filing_date_is_flagged_as_critical(): void
    {
        Rfa::factory()->create([
            'date_filed' => null,
        ]);

        $findings = collect(
            app(DataQualityService::class)->findings()
        )->keyBy('key');

        $this->assertSame(
            1,
            $findings['missing_date_filed']['count']
        );

        $this->assertSame(
            DataQualityService::SEVERITY_CRITICAL,
            $findings['missing_date_filed']['severity']
        );
    }

    public function test_a_second_conference_without_a_first_is_flagged(): void
    {
        Rfa::factory()->create([
            'date_initial_conference' => null,
            'date_second_conference' => '2026-08-18',
        ]);

        $findings = collect(
            app(DataQualityService::class)->findings()
        )->keyBy('key');

        $this->assertSame(
            1,
            $findings['second_conference_without_first']['count']
        );
    }

    public function test_a_finding_disappears_once_the_record_is_corrected(): void
    {
        $rfa = Rfa::factory()->create([
            'date_filed' => null,
        ]);

        $before = collect(
            app(DataQualityService::class)->findings()
        )->keyBy('key');

        $this->assertSame(1, $before['missing_date_filed']['count']);

        $rfa->forceFill([
            'date_filed' => '2026-08-01',
        ])->save();

        $after = collect(
            app(DataQualityService::class)->findings()
        )->keyBy('key');

        $this->assertSame(0, $after['missing_date_filed']['count']);
    }

    public function test_the_governance_page_renders(): void
    {
        Rfa::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.governance.index'))
            ->assertOk()
            ->assertSee('Standing Checks')
            ->assertSee('Import Batch History');
    }

    public function test_import_history_includes_batches_without_metadata(): void
    {
        Rfa::factory()->count(2)->create([
            'import_batch_uuid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.governance.index'))
            ->assertOk()
            ->assertSee('Not recorded');
    }

    public function test_import_history_shows_recorded_metadata(): void
    {
        Rfa::factory()->create([
            'import_batch_uuid' => '22222222-2222-2222-2222-222222222222',
        ]);

        ImportBatch::create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'file_name' => 'august-export.csv',
            'user_id' => $this->admin->id,
            'rows_processed' => 10,
            'rows_imported' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.governance.index'))
            ->assertOk()
            ->assertSee('august-export.csv');
    }

    public function test_findings_can_be_exported(): void
    {
        Rfa::factory()->create(['date_filed' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.governance.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_governance_is_closed_without_the_permission(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($viewer)
            ->get(route('admin.governance.index'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    public function test_settings_fall_back_to_their_declared_defaults(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame(
            SystemSettings::defaultFor(SystemSettings::LISTING_PER_PAGE),
            $settings->get(SystemSettings::LISTING_PER_PAGE)
        );
    }

    public function test_a_setting_can_be_changed(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    SystemSettings::ORGANIZATION_NAME => 'DOLE RO5 MONITORING',
                    SystemSettings::ORGANIZATION_UNIT => 'RFA Report',
                    SystemSettings::REPORT_FOOTER_NOTE => '',
                    SystemSettings::LISTING_PER_PAGE => 20,
                    SystemSettings::AUDIT_RETENTION_DAYS => 365,
                    SystemSettings::BACKUP_RETENTION_COUNT => 5,
                ],
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame(
            'DOLE RO5 MONITORING',
            app(SettingsService::class)
                ->get(SystemSettings::ORGANIZATION_NAME)
        );

        $this->assertSame(
            20,
            app(SettingsService::class)
                ->get(SystemSettings::LISTING_PER_PAGE)
        );
    }

    public function test_an_out_of_range_setting_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    SystemSettings::ORGANIZATION_NAME => 'Name',
                    SystemSettings::ORGANIZATION_UNIT => 'Unit',
                    SystemSettings::LISTING_PER_PAGE => 999,
                    SystemSettings::AUDIT_RETENTION_DAYS => 30,
                    SystemSettings::BACKUP_RETENTION_COUNT => 5,
                ],
            ])
            ->assertSessionHasErrors('settings.' . SystemSettings::LISTING_PER_PAGE);
    }

    public function test_an_unknown_setting_key_is_never_stored(): void
    {
        app(SettingsService::class)->put([
            'totally_made_up' => 'value',
        ]);

        $this->assertDatabaseMissing('system_settings', [
            'key' => 'totally_made_up',
        ]);
    }

    public function test_the_listing_page_size_default_follows_the_setting(): void
    {
        Rfa::factory()->count(15)->create();

        app(SettingsService::class)->put([
            SystemSettings::LISTING_PER_PAGE => 20,
        ]);

        $this->actingAs($this->admin)
            ->get(route('listing'))
            ->assertOk()
            ->assertViewHas('perPage', '20');
    }

    public function test_changing_settings_is_audited(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    SystemSettings::ORGANIZATION_NAME => 'Changed Name',
                    SystemSettings::ORGANIZATION_UNIT => 'Unit',
                    SystemSettings::LISTING_PER_PAGE => 10,
                    SystemSettings::AUDIT_RETENTION_DAYS => 0,
                    SystemSettings::BACKUP_RETENTION_COUNT => 10,
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'record_label' => 'System settings',
        ]);
    }

    public function test_settings_are_closed_to_non_administrators(): void
    {
        $seado = User::factory()
            ->withRole('seado')
            ->create();

        $this->actingAs($seado)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    */

    public function test_the_backup_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Backup Register');
    }

    public function test_backups_are_refused_on_an_unsupported_connection(): void
    {
        /*
        | The test suite runs on SQLite, where the MySQL dump writer would
        | produce a file that could not be restored.
        */

        $this->assertFalse(
            app(BackupService::class)->isSupported()
        );

        $this->expectException(RuntimeException::class);

        app(BackupService::class)->create($this->admin);
    }

    public function test_the_create_button_reports_the_refusal_rather_than_failing(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.backups.store'))
            ->assertSessionHasErrors('backup');

        $this->assertSame(0, DatabaseBackup::query()->count());
    }

    public function test_a_register_entry_with_no_file_is_reported_as_missing(): void
    {
        $backup = DatabaseBackup::create([
            'filename' => 'rfa_backup_2026-09-01_000000.sql',
            'database_name' => 'rfa_monitoring',
            'driver' => 'mysql',
            'size_bytes' => 1024,
            'table_count' => 9,
            'row_count' => 500,
        ]);

        $this->assertFalse($backup->fileExists());

        $this->assertSame(
            1,
            app(BackupService::class)->missingFiles()
        );

        $this->actingAs($this->admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('File Missing');
    }

    public function test_downloading_a_missing_file_reports_an_error(): void
    {
        $backup = DatabaseBackup::create([
            'filename' => 'rfa_backup_gone.sql',
            'size_bytes' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.backups.download', $backup))
            ->assertSessionHasErrors('backup');
    }

    public function test_a_backup_entry_can_be_deleted(): void
    {
        $backup = DatabaseBackup::create([
            'filename' => 'rfa_backup_removable.sql',
            'size_bytes' => 0,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.backups.destroy', $backup))
            ->assertRedirect();

        $this->assertDatabaseMissing('database_backups', [
            'id' => $backup->id,
        ]);
    }

    public function test_backups_are_closed_to_roles_without_the_permission(): void
    {
        $seado = User::factory()
            ->withRole('seado')
            ->create();

        $this->actingAs($seado)
            ->get(route('admin.backups.index'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Retention
    |--------------------------------------------------------------------------
    */

    public function test_pruning_keeps_everything_when_retention_is_zero(): void
    {
        SystemSetting::create([
            'key' => SystemSettings::AUDIT_RETENTION_DAYS,
            'value' => '0',
        ]);

        app(SettingsService::class)->flush();

        Rfa::factory()->create();

        $before = \App\Models\AuditLog::query()->count();

        $this->artisan('rfa:prune-audit')
            ->assertSuccessful();

        $this->assertSame(
            $before,
            \App\Models\AuditLog::query()->count()
        );
    }

    public function test_pruning_removes_entries_past_the_retention_period(): void
    {
        Rfa::factory()->create();

        \App\Models\AuditLog::query()->update([
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('rfa:prune-audit', ['--days' => 365])
            ->assertSuccessful();

        $this->assertSame(
            0,
            \App\Models\AuditLog::query()->count()
        );
    }
}
