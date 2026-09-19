<?php

namespace App\Observers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Rfa;
use App\Models\Role;
use App\Models\RfaActivity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit entry whenever a tracked model is created, updated, or
 * deleted. Registered per model in AppServiceProvider.
 */
class AuditableObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function created(Model $model): void
    {
        $this->auditLogger->record(
            event: 'created',
            subject: $model,
            recordLabel: $this->label($model),
            summary: $this->describeCreation($model)
        );
    }

    public function updating(Model $model): void
    {
        /*
        |--------------------------------------------------------------------------
        | Recorded on "updating", not "updated"
        |--------------------------------------------------------------------------
        |
        | getDirty() and getOriginal() only hold the before/after pair while the
        | save is still pending.
        |
        */

        $changes = $this->auditLogger->changesFor($model);

        if ($changes === []) {
            return;
        }

        $this->auditLogger->record(
            event: 'updated',
            subject: $model,
            recordLabel: $this->label($model),
            summary: $this->auditLogger->summarize($changes),
            changes: $changes
        );
    }

    public function deleted(Model $model): void
    {
        $this->auditLogger->record(
            event: 'deleted',
            subject: $model,
            recordLabel: $this->label($model),
            summary: 'Record deleted'
        );
    }

    /**
     * A label an administrator recognises without opening the record.
     */
    private function label(Model $model): string
    {
        return match (true) {
            $model instanceof Rfa =>
                'RFA ' . $model->displayReference(),

            $model instanceof User =>
                'User ' . $model->name . ' <' . $model->email . '>',

            $model instanceof Role =>
                'Role ' . $model->name,

            default =>
                class_basename($model) . ' #' . $model->getKey(),
        };
    }

    private function describeCreation(Model $model): string
    {
        return match (true) {
            $model instanceof Rfa => 'RFA record created',
            $model instanceof User => 'User account created',
            $model instanceof Role => 'Role created',
            default => 'Record created',
        };
    }

    /**
     * Models the audit trail follows.
     *
     * Deliberately excludes the audit log itself, the case timeline, and
     * notifications: those are already history, and auditing them would
     * only duplicate what they record.
     *
     * @return array<int, class-string<Model>>
     */
    public static function auditedModels(): array
    {
        return [
            Rfa::class,
            User::class,
            Role::class,
        ];
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public static function excludedModels(): array
    {
        return [
            AuditLog::class,
            RfaActivity::class,
            AppNotification::class,
        ];
    }
}
