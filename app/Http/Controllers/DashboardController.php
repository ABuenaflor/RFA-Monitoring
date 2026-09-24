<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Services\PctService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Days shown on the filing trend chart, ending today.
     */
    private const TREND_DAYS = 5;

    public function index(): View
    {
        $counts = [
            'pending' => Rfa::query()
                ->where('monitoring_bucket', 'pending')
                ->count(),

            'ongoing' => Rfa::query()
                ->where('monitoring_bucket', 'ongoing')
                ->count(),

            'disposed' => Rfa::query()
                ->where('monitoring_bucket', 'disposed')
                ->count(),
        ];

        $total = array_sum($counts);

        $percentages = [
            'pending' => $this->percentage($counts['pending'], $total),
            'ongoing' => $this->percentage($counts['ongoing'], $total),
            'disposed' => $this->percentage($counts['disposed'], $total),
        ];

        return view('dashboard', [
            'counts' => $counts,
            'percentages' => $percentages,
            'total' => $total,
            'filingTrend' => $this->filingTrend(),
        ]);
    }

    /**
     * RFAs filed per day over the last few days, split by mode of filing.
     *
     * Always the last TREND_DAYS days ending today — early in a month it
     * reaches back into the previous one. Days with no filings are zero.
     * Filings with no recognisable mode are counted but not plotted.
     *
     * @return array{labels: array<int, string>, onsite: array<int, int>, online: array<int, int>, unplotted: int, from: string, to: string}
     */
    private function filingTrend(): array
    {
        $to = now()->startOfDay();

        $from = $to->copy()->subDays(self::TREND_DAYS - 1);

        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days[$day->toDateString()] = [
                PctService::MODE_ONSITE => 0,
                PctService::MODE_ONLINE => 0,
            ];
        }

        $unplotted = 0;

        $rows = Rfa::query()
            ->whereDate('date_filed', '>=', $from->toDateString())
            ->whereDate('date_filed', '<=', $to->toDateString())
            ->selectRaw('date_filed, mode_of_filing, COUNT(*) as filings')
            ->groupBy('date_filed', 'mode_of_filing')
            ->get();

        foreach ($rows as $row) {
            $date = $row->date_filed->toDateString();

            $mode = PctService::normalizeMode($row->mode_of_filing);

            if ($mode === null || ! isset($days[$date])) {
                $unplotted += (int) $row->filings;

                continue;
            }

            $days[$date][$mode] += (int) $row->filings;
        }

        return [
            'labels' => array_map(
                fn (string $date) => Carbon::parse($date)->format('M j'),
                array_keys($days)
            ),
            'onsite' => array_column($days, PctService::MODE_ONSITE),
            'online' => array_column($days, PctService::MODE_ONLINE),
            'unplotted' => $unplotted,
            'from' => $from->format('M j'),
            'to' => $to->format('M j, Y'),
        ];
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }
}