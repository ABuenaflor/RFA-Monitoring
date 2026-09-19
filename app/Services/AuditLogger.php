<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Single entry point for writing the system audit trail.
 *
 * Attribute values that should never be stored in plain view — passwords,
 * tokens — are stripped before anything is written.
 */
class AuditLogger
{
    /**
     * Attributes that are never recorded, whatever model they belong to.
     *
     * @var array<int, string>
     */
    private const REDACTED = [
        'password',
        'remember_token',
        'import_payload',
    ];

    /**
     * Bookkeeping columns that change on their own and would otherwise fill
     * the trail with entries nobody needs to review.
     *
     * @var array<int, string>
     */
    private const NOT_WORTH_AUDITING = [
        'created_at',
        'updated_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * Bulk operations pause recording and write a single summary instead of
     * one row per affected record.
     */
    private static bool $recording = true;

    public static function pause(): void
    {
        self::$recording = false;
    }

    public static function resume(): void
    {
        self::$recording = true;
    }

    public static function isRecording(): bool
    {
        return self::$recording;
    }

    /**
     * @param  array<int|string, mixed>|null  $changes
     */
    public function record(
        string $event,
        ?Model $subject = null,
        ?string $recordLabel = null,
        ?string $summary = null,
        ?array $changes = null,
        ?User $actor = null
    ): ?AuditLog {
        if (! self::$recording) {
            return null;
        }

        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,

            'user_name' => $actor?->name,

            'event' => $event,

            'auditable_type' => $subject
                ? $subject::class
                : null,

            'auditable_id' => $subject?->getKey(),

            'record_label' => $recordLabel,

            'change_summary' => $summary
                ? mb_substr($summary, 0, 500)
                : null,

            'changes' => $changes,

            'ip_address' => $this->clientIp(),

            'user_agent' => $this->userAgent(),
        ]);
    }

    /**
     * Before/after values for a model that is being saved, with sensitive
     * attributes removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function changesFor(Model $model): array
    {
        $changes = [];

        foreach (array_keys($model->getDirty()) as $attribute) {
            if (in_array($attribute, self::REDACTED, true)) {
                $changes[] = [
                    'field' => $attribute,
                    'from' => '••••••',
                    'to' => '••••••',
                ];

                continue;
            }

            if (in_array($attribute, self::NOT_WORTH_AUDITING, true)) {
                continue;
            }

            $changes[] = [
                'field' => $attribute,

                'from' => $this->stringify(
                    $model->getOriginal($attribute)
                ),

                'to' => $this->stringify(
                    $model->getAttribute($attribute)
                ),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function summarize(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        $fields = array_map(
            fn (array $change) => \Illuminate\Support\Str::headline(
                (string) $change['field']
            ),
            $changes
        );

        return count($fields) > 6
            ? implode(', ', array_slice($fields, 0, 6))
                . ' and ' . (count($fields) - 6) . ' more'
            : implode(', ', $fields);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return mb_substr((string) $value, 0, 255);
    }

    private function clientIp(): ?string
    {
        return app()->runningInConsole()
            ? null
            : Request::ip();
    }

    private function userAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        $agent = Request::userAgent();

        return $agent
            ? mb_substr($agent, 0, 500)
            : null;
    }
}
