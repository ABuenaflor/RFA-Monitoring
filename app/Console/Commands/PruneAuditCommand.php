<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\SettingsService;
use App\Support\SystemSettings;
use Illuminate\Console\Command;

class PruneAuditCommand extends Command
{
    protected $signature = 'rfa:prune-audit {--days= : Override the configured retention period}';

    protected $description = 'Remove audit entries older than the configured retention period.';

    public function handle(
        SettingsService $settings
    ): int {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) $settings->get(
                SystemSettings::AUDIT_RETENTION_DAYS
            );

        if ($days <= 0) {
            $this->info(
                'Audit retention is set to keep entries indefinitely. Nothing pruned.'
            );

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(
            "Removed {$deleted} audit entries older than {$cutoff->toDateString()}."
        );

        return self::SUCCESS;
    }
}
