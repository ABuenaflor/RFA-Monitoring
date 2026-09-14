<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScanPctCommand extends Command
{
    protected $signature = 'rfa:scan-pct';

    protected $description = 'Recalculate PCT notifications for every active RFA.';

    public function handle(
        NotificationService $notifications
    ): int {
        $this->info('Scanning active RFAs for PCT breaches...');

        $result = $notifications->scanPct();

        $this->table(
            ['Cases scanned', 'Alerts current', 'Alerts cleared'],
            [[
                $result['cases_scanned'],
                $result['alerts'],
                $result['cleared'],
            ]]
        );

        return self::SUCCESS;
    }
}
