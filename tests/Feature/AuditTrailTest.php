<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Rfa;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditTrailTest extends TestCase
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

    public function test_creating_a_record_is_audited(): void
    {
        $this->actingAs($this->admin);

        $rfa = Rfa::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => Rfa::class,
            'auditable_id' => $rfa->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_updating_a_record_stores_before_and_after_values(): void
    {
        $rfa = Rfa::factory()->create([
            'requesting_party' => 'Before',
        ]);

        $this->actingAs($this->admin)
            ->put(route('rfas.details', $rfa), [
                'requesting_party' => 'After',
            ]);

        $entry = AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_id', $rfa->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);

        $change = collect($entry->changes)
            ->firstWhere('field', 'requesting_party');

        $this->assertSame('Before', $change['from']);

        $this->assertSame('After', $change['to']);
    }

    public function test_deleting_a_record_is_audited(): void
    {
        $victim = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.access.destroy', $victim));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'deleted',
            'auditable_type' => User::class,
            'auditable_id' => $victim->id,
        ]);
    }

    public function test_a_password_is_never_written_to_the_trail_in_the_clear(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $viewer), [
                'name' => $viewer->name,
                'email' => $viewer->email,
                'role_id' => $viewer->role_id,
                'status' => 'active',
                'password' => 'sensitive-pass-9',
                'password_confirmation' => 'sensitive-pass-9',
            ]);

        $entries = AuditLog::query()
            ->where('auditable_id', $viewer->id)
            ->get();

        foreach ($entries as $entry) {
            $this->assertStringNotContainsString(
                'sensitive-pass-9',
                json_encode($entry->changes) ?: ''
            );
        }

        $passwordChange = $entries
            ->flatMap(fn ($entry) => $entry->changes ?? [])
            ->firstWhere('field', 'password');

        $this->assertNotNull($passwordChange);

        $this->assertSame('••••••', $passwordChange['to']);
    }

    public function test_sign_in_and_sign_out_are_audited(): void
    {
        $user = User::factory()
            ->administrator()
            ->create([
                'password' => Hash::make('correct-horse-9'),
            ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login',
            'user_id' => $user->id,
        ]);

        $this->post('/logout');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_failed_sign_in_is_audited(): void
    {
        $user = User::factory()
            ->administrator()
            ->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'record_label' => $user->email,
        ]);
    }

    public function test_a_routine_sign_in_does_not_create_an_update_entry(): void
    {
        $user = User::factory()
            ->administrator()
            ->create([
                'password' => Hash::make('correct-horse-9'),
            ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_recording_can_be_paused_for_bulk_work(): void
    {
        AuditLogger::pause();

        Rfa::factory()->create();

        AuditLogger::resume();

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('event', 'created')
                ->where('auditable_type', Rfa::class)
                ->count()
        );

        Rfa::factory()->create();

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('event', 'created')
                ->where('auditable_type', Rfa::class)
                ->count()
        );
    }

    public function test_the_audit_section_is_hidden_without_permission(): void
    {
        $seado = User::factory()
            ->withRole('seado')
            ->create();

        $this->actingAs($seado)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertDontSee('Recent System Events')
            ->assertSee('Your role does not include permission');
    }

    public function test_an_administrator_sees_the_audit_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Recent System Events');
    }
}
