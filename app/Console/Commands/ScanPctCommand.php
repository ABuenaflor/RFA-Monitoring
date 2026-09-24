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
            ['Cases scanned', 'Alerts current', 'Alerts cleared', 'Emails sent', 'Emails failed'],
            [[
                $result['cases_scanned'],
                $result['alerts'],
                $result['cleared'],
                $result['emails_sent'],
                $result['emails_failed'],
            ]]
        );

        if ($result['emails_failed'] > 0) {
            $this->warn(
                'Some PCT emails could not be sent and will be retried on the'
                . ' next scan. Check the mail settings and storage/logs.'
            );
        }

        return self::SUCCESS;
    }
}
