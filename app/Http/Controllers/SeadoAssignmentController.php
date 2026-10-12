<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Models\RouletteExclusion;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RfaWorkflowService;
use App\Support\Workflow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SEADO Assignment: how many RFAs each SEADO has handled, per province,
 * and a queue of open cases with no SEADO yet. "Assign to a SEADO" offers
 * the five SEADOs in the case's office who have handled the fewest RFAs.
 *
 * SEADOs are everyone named as SEADO on a case plus every active user with
 * the SEADO role, matched to each other by exact name.
 */
class SeadoAssignmentController extends Controller
{
    public const SUGGESTIONS = 5;

    private const PER_PAGE = 15;

    public function __construct(
        private readonly RfaWorkflowService $workflow,
        private readonly NotificationService $notifications
    ) {
    }

    public function index(Request $request): View
    {
        $offices = config('offices.list', []);

        $officeFilter = (string) $request->query('office', '');

        if (! in_array($officeFilter, $offices, true)) {
            $officeFilter = '';
        }

        $search = trim((string) $request->query('search', ''));

        $sourceStatus = trim((string) $request->query('source_status', ''));

        /*
        |--------------------------------------------------------------------------
        | Workload per SEADO
        |--------------------------------------------------------------------------
        */

        $roster = $this->roster($officeFilter ?: null);

        $exclusions = RouletteExclusion::query()
            ->with('excluder:id,name')
            ->orderBy('seado_name')
            ->get()
            ->keyBy('seado_key');

        /*
        |--------------------------------------------------------------------------
        | Open cases waiting for a SEADO
        |--------------------------------------------------------------------------
        */

        $queueQuery = $this->awaitingQuery()
            ->when($officeFilter !== '', fn (Builder $query) => $query->where('office', $officeFilter));

        $sourceStatuses = (clone $queueQuery)
            ->whereNotNull('source_case_status')
            ->distinct()
            ->orderBy('source_case_status')
            ->pluck('source_case_status');

        $queue = $queueQuery
            ->when($sourceStatus !== '', fn (Builder $query) => $query->where('source_case_status', $sourceStatus))
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('docket_no', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('requesting_party', 'like', "%{$search}%")
                    ->orWhere('responding_party', 'like', "%{$search}%")))
            ->orderBy('date_filed')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        /*
        | The five suggestions depend on each case's own office.
        */

        $suggestions = $queue->getCollection()
            ->map(fn (Rfa $rfa) => (string) $rfa->office)
            ->unique()
            ->mapWithKeys(fn (string $office) => [
                $office => $this->suggestionsFor($office !== '' ? $office : null),
            ]);

        return view('seado-assignment.index', [
            'offices' => $offices,
            'officeFilter' => $officeFilter,
            'officeLabel' => array_search($officeFilter, $offices, true) ?: null,
            'search' => $search,
            'sourceStatus' => $sourceStatus,
            'sourceStatuses' => $sourceStatuses,
            'roster' => $roster,

            /*
            | The roulette draws the five least-handled SEADOs for the selected
            | province, skipping excluded ones — the next least-handled take
            | their place.
            */

            'roulette' => $roster
                ->reject(fn (array $seado) => $exclusions->has($seado['key']))
                ->take(self::SUGGESTIONS)
                ->map(fn (array $seado) => [
                    'name' => $seado['name'],
                    'total' => $seado['total'],
                    'open' => $seado['open'],
                    'disposed' => $seado['disposed'],
                    'offices' => $seado['offices'],
                    'has_account' => $seado['has_account'],
                ])
                ->values(),
            'rosterTotals' => [
                'seados' => $roster->count(),
                'handled' => $roster->sum('total'),
                'open' => $roster->sum('open'),
            ],
            'queue' => $queue,
            'suggestions' => $suggestions,
            'today' => Carbon::today(),
            'exclusions' => $exclusions->values(),
            'excludable' => $roster
                ->reject(fn (array $seado) => $exclusions->has($seado['key']))
                ->sortBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function assign(Request $request, Rfa $rfa): RedirectResponse
    {
        $data = $request->validate([
            'seado_name' => ['required', 'string', 'max:150'],
            'date_assigned_seado' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'seado_name.required' => 'Choose a SEADO from the list.',
            'date_assigned_seado.before_or_equal' => 'The assignment date cannot be in the future.',
        ]);

        if (filled($rfa->seado_name) || $rfa->seado_id !== null) {
            throw ValidationException::withMessages([
                'seado_name' => $rfa->displayReference() . ' is already assigned to '
                    . ($rfa->seado_name ?: 'a SEADO') . '. Change it on the case page instead.',
            ]);
        }

        $seado = $this->roster(null)->firstWhere('key', $this->key($data['seado_name']));

        if ($seado === null) {
            throw ValidationException::withMessages([
                'seado_name' => 'That SEADO is not on the SEADO list.',
            ]);
        }

        $attributes = [
            'seado_id' => $seado['user_id'],
            'seado_name' => $seado['user_id'] ? User::query()->find($seado['user_id'])?->name : $seado['name'],
            'date_assigned_seado' => $data['date_assigned_seado'],
        ];

        if ($this->isBefore((string) $rfa->status, Workflow::ASSIGNED_TO_SEADO)) {
            $attributes['status'] = Workflow::ASSIGNED_TO_SEADO;
        }

        $this->guardChronology($rfa, $attributes);

        $this->workflow->apply(
            $rfa,
            $attributes,
            $request->user(),
            'assignment',
            'Assigned to SEADO',
            'Assigned from SEADO Assignment to ' . $attributes['seado_name']
                . ' (' . $seado['total'] . ' RFAs handled at the time).'
        );

        if ($seado['user_id'] !== null) {
            $recipient = User::query()->find($seado['user_id']);

            if ($recipient?->isActive()) {
                $this->notifications->notifyAssignment($recipient, $rfa, 'SEADO');
            }
        }

        return redirect()
            ->back()
            ->with('status', $rfa->displayReference() . ' assigned to ' . $attributes['seado_name'] . '.');
    }

    /*
    |--------------------------------------------------------------------------
    | Roulette exclusions
    |--------------------------------------------------------------------------
    */

    public function exclude(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seado_name' => ['required', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'seado_name.required' => 'Choose a SEADO to exclude.',
        ]);

        $key = $this->key($data['seado_name']);

        $seado = $this->roster(null)->firstWhere('key', $key);

        if ($seado === null) {
            throw ValidationException::withMessages([
                'seado_name' => 'That SEADO is not on the SEADO list.',
            ]);
        }

        if (RouletteExclusion::query()->where('seado_key', $key)->exists()) {
            throw ValidationException::withMessages([
                'seado_name' => $seado['name'] . ' is already excluded from the roulette.',
            ]);
        }

        RouletteExclusion::create([
            'seado_key' => $key,
            'seado_name' => $seado['name'],
            'reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
            'excluded_by' => $request->user()->id,
        ]);

        return $this->backToRoulette($seado['name'] . ' is excluded from the roulette.');
    }

    public function include(RouletteExclusion $exclusion): RedirectResponse
    {
        $exclusion->delete();

        return $this->backToRoulette($exclusion->seado_name . ' is back in the roulette.');
    }

    private function backToRoulette(string $message): RedirectResponse
    {
        return redirect()
            ->to(url()->previous(route('seado-assignment.index')) . '#seado-roulette')
            ->with('status', $message);
    }

    /*
    |--------------------------------------------------------------------------
    | Roster
    |--------------------------------------------------------------------------
    */

    /**
     * Every SEADO with their handled-case counts, fewest handled first.
     *
     * With an office, counts only that office's cases, and lists only SEADOs
     * who handle cases there or whose account is in that office.
     *
     * @return Collection<int, array{key: string, name: string, user_id: ?int, total: int, open: int, disposed: int, offices: array<int, string>, has_account: bool}>
     */
    public function roster(?string $office): Collection
    {
        $rows = Rfa::query()
            ->where(fn (Builder $query) => $query
                ->whereNotNull('seado_name')
                ->where('seado_name', '!=', ''))
            ->when($office !== null, fn (Builder $query) => $query->where('office', $office))
            ->selectRaw('seado_name, office, COUNT(*) AS total')
            ->selectRaw(
                'SUM(CASE WHEN date_disposed IS NULL AND monitoring_bucket != ? THEN 1 ELSE 0 END) AS open_cases',
                [Workflow::BUCKET_DISPOSED]
            )
            ->groupBy('seado_name', 'office')
            ->get();

        $roster = [];

        foreach ($rows as $row) {
            $key = $this->key($row->seado_name);

            $roster[$key] ??= $this->blank($key, trim($row->seado_name));

            $roster[$key]['total'] += (int) $row->total;

            $roster[$key]['open'] += (int) $row->open_cases;

            if ($row->office) {
                $roster[$key]['offices'][] = $row->office;
            }
        }

        /*
        | SEADO accounts: link them to their name, and list them even before
        | they have handled a case.
        */

        $officeLabel = $office !== null
            ? array_search($office, config('offices.list', []), true)
            : null;

        $accounts = User::query()
            ->active()
            ->get(['id', 'name', 'office', 'role_id'])
            ->load('role');

        foreach ($accounts as $user) {
            $key = $this->key($user->name);

            $isSeadoRole = $user->role?->slug === 'seado';

            if (isset($roster[$key])) {
                $roster[$key]['user_id'] = $user->id;
                $roster[$key]['has_account'] = true;

                continue;
            }

            if ($isSeadoRole && ($office === null || $user->office === $officeLabel)) {
                $roster[$key] = $this->blank($key, $user->name);
                $roster[$key]['user_id'] = $user->id;
                $roster[$key]['has_account'] = true;
            }
        }

        return collect($roster)
            ->map(function (array $seado) {
                $seado['offices'] = array_values(array_unique($seado['offices']));
                $seado['disposed'] = $seado['total'] - $seado['open'];

                return $seado;
            })
            ->sort(fn (array $a, array $b) => [$a['total'], $a['open'], $a['name']] <=> [$b['total'], $b['open'], $b['name']])
            ->values();
    }

    /**
     * The five SEADOs in an office who have handled the fewest RFAs there.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function suggestionsFor(?string $office): Collection
    {
        return $this->roster($office)->take(self::SUGGESTIONS)->values();
    }

    /**
     * Open cases with no SEADO.
     */
    private function awaitingQuery(): Builder
    {
        return Rfa::query()
            ->whereNull('seado_id')
            ->where(fn (Builder $query) => $query
                ->whereNull('seado_name')
                ->orWhere('seado_name', ''))
            ->whereNull('date_disposed')
            ->where('monitoring_bucket', '!=', Workflow::BUCKET_DISPOSED);
    }

    /**
     * @return array<string, mixed>
     */
    private function blank(string $key, string $name): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'user_id' => null,
            'has_account' => false,
            'total' => 0,
            'open' => 0,
            'disposed' => 0,
            'offices' => [],
        ];
    }

    private function key(string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)));
    }

    private function isBefore(string $status, string $target): bool
    {
        $order = Workflow::statusKeys();

        $current = array_search($status, $order, true);

        return $current === false
            || $current < array_search($target, $order, true);
    }

    /**
     * Refuse an assignment that would create a new chronology problem, such
     * as a SEADO assignment date after the 1st conference.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function guardChronology(Rfa $rfa, array $attributes): void
    {
        $candidate = clone $rfa;

        $candidate->fill($attributes);

        $introduced = array_values(array_diff(
            $this->workflow->chronologyIssues($candidate),
            $this->workflow->chronologyIssues($rfa)
        ));

        if ($introduced !== []) {
            throw ValidationException::withMessages(['date_assigned_seado' => $introduced]);
        }
    }
}
