<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
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
        ]);
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }
}