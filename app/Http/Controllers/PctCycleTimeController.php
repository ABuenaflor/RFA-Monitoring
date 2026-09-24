<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Services\PctService;
use App\Support\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Process Cycle Time — a visual walk through the five PCT checkpoints:
 * where each clock starts and stops, how each case is moving through
 * them, and how every checkpoint is performing overall.
 */
class PctCycleTimeController extends Controller
{
    private const PER_PAGE = 15;

    public function index(
        Request $request,
        PctService $pctService
    ): View {
        $asOf = now()->startOfDay();

        $definitions = PctService::definitions();

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        $offices = config('offices.list', []);

        $officeFilter = (string) $request->query('office', '');

        if (! in_array($officeFilter, $offices, true)) {
            $officeFilter = '';
        }

        $caseFilter = (string) $request->query('cases', 'open');

        if (! in_array($caseFilter, ['open', 'disposed', 'all'], true)) {
            $caseFilter = 'open';
        }

        $search = trim((string) $request->query('search', ''));

        $query = Rfa::query()
            ->when(
                $officeFilter !== '',
                fn (Builder $query) => $query->where('office', $officeFilter)
            );

        /*
        |--------------------------------------------------------------------------
        | Checkpoint performance (office-wide, not limited by the case list)
        |--------------------------------------------------------------------------
        */

        $stats = array_fill_keys(array_keys($definitions), [
            'active' => 0,
            'active_beyond' => 0,
            'completed' => 0,
            'compliant' => 0,
            'total_days' => 0,
            'unrated' => 0,
        ]);

        foreach ((clone $query)->get() as $rfa) {
            foreach ($pctService->evaluate($rfa, $asOf)['checkpoints'] as $key => $checkpoint) {
                $stat = &$stats[$key];

                match ($checkpoint['state']) {
                    'active' => $stat['active']++,
                    'completed' => $stat['completed']++,
                    default => $stat['unrated']++,
                };

                if ($checkpoint['state'] === 'active' && $checkpoint['classification_key'] === 'beyond') {
                    $stat['active_beyond']++;
                }

                if ($checkpoint['state'] === 'completed') {
                    $stat['total_days'] += $checkpoint['days'];

                    if ($checkpoint['is_compliant']) {
                        $stat['compliant']++;
                    }
                }

                unset($stat);
            }
        }

        foreach ($stats as &$stat) {
            $stat['average_days'] = $stat['completed'] > 0
                ? round($stat['total_days'] / $stat['completed'], 1)
                : null;

            $stat['compliance_rate'] = $stat['completed'] > 0
                ? round($stat['compliant'] / $stat['completed'] * 100, 1)
                : null;
        }

        unset($stat);

        /*
        |--------------------------------------------------------------------------
        | Case timelines
        |--------------------------------------------------------------------------
        */

        $cases = $query
            ->when($caseFilter === 'open', fn (Builder $query) => $query
                ->whereNull('date_disposed')
                ->where('monitoring_bucket', '!=', Workflow::BUCKET_DISPOSED))
            ->when($caseFilter === 'disposed', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->whereNotNull('date_disposed')
                    ->orWhere('monitoring_bucket', Workflow::BUCKET_DISPOSED)))
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('docket_no', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('requesting_party', 'like', "%{$search}%")
                    ->orWhere('responding_party', 'like', "%{$search}%")))
            ->orderByDesc('date_filed')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $timelines = $cases->getCollection()->mapWithKeys(
            fn (Rfa $rfa) => [
                $rfa->id => $pctService->evaluate($rfa, $asOf),
            ]
        );

        return view('pct.cycle-time', [
            'asOf' => $asOf,
            'definitions' => $definitions,
            'stats' => $stats,
            'cases' => $cases,
            'timelines' => $timelines,
            'offices' => $offices,
            'officeFilter' => $officeFilter,
            'officeLabel' => array_search($officeFilter, $offices, true) ?: null,
            'caseFilter' => $caseFilter,
            'search' => $search,
        ]);
    }
}
