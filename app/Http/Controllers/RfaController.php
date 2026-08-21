<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RfaController extends Controller
{
    /**
     * Display the master RFA listing.
     */
    public function index(Request $request): View
    {
        /*
        |--------------------------------------------------------------------------
        | Allowed sorting columns
        |--------------------------------------------------------------------------
        */

        $allowedSorts = [
            'reference_no',
            'requesting_party',
            'responding_party',
            'date_filed',
            'status',
            'monitoring_bucket',
            'updated_at',
        ];

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'date_filed';
        }

        $direction = strtolower(
            $request->string('direction')->toString()
        );

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        /*
        |--------------------------------------------------------------------------
        | Page size
        |--------------------------------------------------------------------------
        */

        $perPage = strtolower(
            $request->string('per_page', '10')->toString()
        );

        if (! in_array(
            $perPage,
            ['10', '20', '50', 'all'],
            true
        )) {
            $perPage = '10';
        }

        /*
        |--------------------------------------------------------------------------
        | Base query
        |--------------------------------------------------------------------------
        */

        $query = Rfa::query();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        $search = trim(
            $request->string('search')->toString()
        );

        if ($search !== '') {
            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'reference_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'requesting_party',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'responding_party',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Monitoring filter
        |--------------------------------------------------------------------------
        */

        $monitoringBucket = $request
            ->string('monitoring_bucket')
            ->toString();

        if (
            in_array(
                $monitoringBucket,
                ['pending', 'ongoing', 'disposed'],
                true
            )
        ) {
            $query->where(
                'monitoring_bucket',
                $monitoringBucket
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Workflow status filter
        |--------------------------------------------------------------------------
        */

        $status = $request
            ->string('status')
            ->toString();

        if ($status !== '') {
            $query->where('status', $status);
        }

        /*
        |--------------------------------------------------------------------------
        | Filing mode filter
        |--------------------------------------------------------------------------
        */

        $modeOfFiling = $request
            ->string('mode_of_filing')
            ->toString();

        if ($modeOfFiling !== '') {
            $query->where(
                'mode_of_filing',
                $modeOfFiling
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Disposition filter
        |--------------------------------------------------------------------------
        */

        $disposition = $request
            ->string('disposition_status')
            ->toString();

        if ($disposition !== '') {
            $query->where(
                'disposition_status',
                $disposition
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date filed range
        |--------------------------------------------------------------------------
        */

        $dateFrom = $request
            ->string('date_from')
            ->toString();

        if ($dateFrom !== '') {
            $query->whereDate(
                'date_filed',
                '>=',
                $dateFrom
            );
        }

        $dateTo = $request
            ->string('date_to')
            ->toString();

        if ($dateTo !== '') {
            $query->whereDate(
                'date_filed',
                '<=',
                $dateTo
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Apply sorting
        |--------------------------------------------------------------------------
        */

        $query->orderBy(
            $sort,
            $direction
        );

        /*
        |--------------------------------------------------------------------------
        | Stable secondary sorting
        |--------------------------------------------------------------------------
        */

        if ($sort !== 'id') {
            $query->orderBy(
                'id',
                'desc'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Result count before pagination
        |--------------------------------------------------------------------------
        */

        $filteredCount = (clone $query)->count();

        /*
        |--------------------------------------------------------------------------
        | Pagination / Show All
        |--------------------------------------------------------------------------
        */

        if ($perPage === 'all') {
            $rfas = $query->get();
            $paginator = null;
        } else {
            $paginator = $query
                ->paginate((int) $perPage)
                ->withQueryString();

            $rfas = $paginator
                ->getCollection();
        }

        /*
        |--------------------------------------------------------------------------
        | Dynamic filter options
        |--------------------------------------------------------------------------
        */

        $statuses = Rfa::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $filingModes = Rfa::query()
            ->whereNotNull('mode_of_filing')
            ->where('mode_of_filing', '!=', '')
            ->distinct()
            ->orderBy('mode_of_filing')
            ->pluck('mode_of_filing');

        $dispositions = Rfa::query()
            ->whereNotNull('disposition_status')
            ->where('disposition_status', '!=', '')
            ->distinct()
            ->orderBy('disposition_status')
            ->pluck('disposition_status');

        /*
        |--------------------------------------------------------------------------
        | Summary counts - unaffected by current filters
        |--------------------------------------------------------------------------
        */

        $summary = [
            'total' => Rfa::count(),

            'pending' => Rfa::where(
                'monitoring_bucket',
                'pending'
            )->count(),

            'ongoing' => Rfa::where(
                'monitoring_bucket',
                'ongoing'
            )->count(),

            'disposed' => Rfa::where(
                'monitoring_bucket',
                'disposed'
            )->count(),
        ];

        return view('rfas.index', [
            'rfas' => $rfas,
            'paginator' => $paginator,
            'filteredCount' => $filteredCount,

            'statuses' => $statuses,
            'filingModes' => $filingModes,
            'dispositions' => $dispositions,

            'summary' => $summary,

            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }
}
