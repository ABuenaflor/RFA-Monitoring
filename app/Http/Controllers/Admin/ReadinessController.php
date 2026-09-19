<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReleaseSignoff;
use App\Models\UatResult;
use App\Services\ReadinessService;
use App\Support\UatPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReadinessController extends Controller
{
    public function __construct(
        private readonly ReadinessService $readiness
    ) {
    }

    public function index(): View
    {
        $results = UatResult::query()
            ->with('tester')
            ->get()
            ->keyBy('case_key');

        $passed = $results
            ->where('status', UatPlan::STATUS_PASSED)
            ->count();

        return view('admin.readiness.index', [
            'checks' => $this->readiness->checks(),

            'checkSummary' => $this->readiness->summary(),

            'areas' => UatPlan::areas(),

            'results' => $results,

            'statuses' => UatPlan::statuses(),

            'uatSummary' => [
                'total' => UatPlan::totalCases(),

                'passed' => $passed,

                'failed' => $results
                    ->where('status', UatPlan::STATUS_FAILED)
                    ->count(),

                'blocked' => $results
                    ->where('status', UatPlan::STATUS_BLOCKED)
                    ->count(),

                'pending' => UatPlan::totalCases()
                    - $results
                        ->whereIn('status', [
                            UatPlan::STATUS_PASSED,
                            UatPlan::STATUS_FAILED,
                            UatPlan::STATUS_BLOCKED,
                            UatPlan::STATUS_NOT_APPLICABLE,
                        ])
                        ->count(),
            ],

            'signoffs' => ReleaseSignoff::query()
                ->with('signer')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Record the outcome of a single UAT case.
     */
    public function recordResult(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_key' =>
                [
                    'required',
                    Rule::in(UatPlan::caseKeys()),
                ],

            'status' =>
                [
                    'required',
                    Rule::in(UatPlan::statusKeys()),
                ],

            'notes' =>
                ['nullable', 'string', 'max:2000'],
        ]);

        UatResult::updateOrCreate(
            ['case_key' => $validated['case_key']],
            [
                'status' => $validated['status'],

                'notes' => $validated['notes'] ?? null,

                'tested_by' => $request->user()->id,

                'tested_at' => now(),
            ]
        );

        return back()->with(
            'status',
            'Test result recorded.'
        );
    }

    /**
     * Record a dated acceptance of a release.
     */
    public function signOff(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'version' =>
                ['required', 'string', 'max:50'],

            'environment' =>
                ['required', 'string', 'max:50'],

            'summary' =>
                ['nullable', 'string', 'max:500'],
        ]);

        $checkSummary = $this->readiness->summary();

        $passed = UatResult::query()
            ->where('status', UatPlan::STATUS_PASSED)
            ->count();

        ReleaseSignoff::create([
            'version' => $validated['version'],

            'environment' => $validated['environment'],

            'summary' => $validated['summary'] ?? null,

            /*
            | The readiness picture is frozen into the record, so a later
            | sign-off cannot be read as covering a state it never saw.
            */

            'cases_passed' => $passed,

            'cases_total' => UatPlan::totalCases(),

            'checks_failing' => $checkSummary['failing'],

            'signed_by' => $request->user()->id,

            'signed_at' => now(),
        ]);

        return back()->with(
            'status',
            'Release sign-off recorded.'
        );
    }
}
