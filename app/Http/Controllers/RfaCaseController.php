<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PctService;
use App\Services\RfaWorkflowService;
use App\Support\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RfaCaseController extends Controller
{
    public function __construct(
        private readonly RfaWorkflowService $workflow,
        private readonly NotificationService $notifications
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Case Detail
    |--------------------------------------------------------------------------
    */

    public function show(
        Rfa $rfa,
        PctService $pctService
    ): View {
        $rfa->load([
            'activities.user',
            'interviewer',
            'seado',
        ]);

        return view('rfas.show', [
            'rfa' => $rfa,

            'pct' => $pctService->evaluate($rfa),

            'issues' => $this->workflow->chronologyIssues($rfa),

            'suggestedStatus' =>
                $this->workflow->deriveStatus($rfa),

            'statuses' => Workflow::statuses(),

            'assignableUsers' => User::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'office', 'position']),

            'dispositionStatuses' =>
                $this->distinctValues('disposition_status'),

            'dispositionModes' =>
                $this->distinctValues('disposition_mode'),

            'offices' =>
                $this->distinctValues('office'),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Case Information
    |--------------------------------------------------------------------------
    */

    public function updateDetails(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $data = $request->validate([
            'docket_no' =>
                ['nullable', 'string', 'max:100'],

            'office' =>
                ['nullable', 'string', 'max:50'],

            'requesting_party' =>
                ['nullable', 'string', 'max:255'],

            'responding_party' =>
                ['nullable', 'string', 'max:255'],

            'company_address' =>
                ['nullable', 'string', 'max:1000'],

            'contact_no' =>
                ['nullable', 'string', 'max:100'],

            'industry' =>
                ['nullable', 'string', 'max:255'],

            'industry_code' =>
                ['nullable', 'string', 'max:50'],

            'size_of_enterprise' =>
                ['nullable', 'string', 'max:50'],

            'filer_class' =>
                ['nullable', 'string', 'max:100'],

            'total_employment' =>
                ['nullable', 'integer', 'min:0', 'max:1000000'],

            'workers_involved' =>
                ['nullable', 'integer', 'min:0', 'max:1000000'],

            'male_workers' =>
                ['nullable', 'integer', 'min:0', 'max:1000000'],

            'female_workers' =>
                ['nullable', 'integer', 'min:0', 'max:1000000'],

            'issues' =>
                ['nullable', 'string', 'max:5000'],
        ]);

        $this->workflow->apply(
            $rfa,
            $data,
            $request->user(),
            'details',
            'Case information updated'
        );

        return $this->back(
            $rfa,
            'Case information saved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    public function updateAssignment(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $validated = $request->validate([
            'interviewer_user_id' =>
                ['nullable', Rule::exists('users', 'id')],

            'interviewer_name' =>
                ['nullable', 'string', 'max:150'],

            'date_assigned_interviewer' =>
                ['nullable', 'date'],

            'seado_user_id' =>
                ['nullable', Rule::exists('users', 'id')],

            'seado_name' =>
                ['nullable', 'string', 'max:150'],

            'date_assigned_seado' =>
                ['nullable', 'date'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Account selection wins over the typed name
        |--------------------------------------------------------------------------
        |
        | Imported records only ever carry a name, so the free-text field stays
        | available. When a system account is chosen, both are kept in step.
        |
        */

        $data = [
            'date_assigned_interviewer' =>
                $validated['date_assigned_interviewer'] ?? null,

            'date_assigned_seado' =>
                $validated['date_assigned_seado'] ?? null,
        ];

        $data += $this->resolveAssignee(
            $validated['interviewer_user_id'] ?? null,
            $validated['interviewer_name'] ?? null,
            'interviewer_id',
            'interviewer_name'
        );

        $data += $this->resolveAssignee(
            $validated['seado_user_id'] ?? null,
            $validated['seado_name'] ?? null,
            'seado_id',
            'seado_name'
        );

        $this->guardChronology($rfa, $data);

        $previousInterviewer = $rfa->interviewer_id;

        $previousSeado = $rfa->seado_id;

        $this->workflow->apply(
            $rfa,
            $data,
            $request->user(),
            'assignment',
            'Assignment updated'
        );

        $this->announceAssignment(
            $rfa,
            $previousInterviewer,
            $previousSeado
        );

        return $this->back(
            $rfa,
            'Assignment saved.'
        );
    }

    /**
     * Tell an officer when a case has just been handed to them.
     *
     * Only a change of account notifies — re-saving the same assignment does
     * not put the case back in their queue.
     */
    private function announceAssignment(
        Rfa $rfa,
        ?int $previousInterviewer,
        ?int $previousSeado
    ): void {
        foreach ([
            ['id' => $rfa->interviewer_id, 'was' => $previousInterviewer, 'role' => 'Interviewer'],
            ['id' => $rfa->seado_id, 'was' => $previousSeado, 'role' => 'SEADO'],
        ] as $assignment) {
            if (
                $assignment['id'] === null
                || $assignment['id'] === $assignment['was']
            ) {
                continue;
            }

            $recipient = User::query()->find($assignment['id']);

            if ($recipient === null || ! $recipient->isActive()) {
                continue;
            }

            $this->notifications->notifyAssignment(
                $recipient,
                $rfa,
                $assignment['role']
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Workflow Progress
    |--------------------------------------------------------------------------
    */

    public function updateWorkflow(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $data = $request->validate([
            'status' =>
                [
                    'required',
                    Rule::in(Workflow::statusKeys()),
                ],

            'date_filed' =>
                ['nullable', 'date'],

            'date_interview' =>
                ['nullable', 'date'],

            'date_validated' =>
                ['nullable', 'date'],

            'date_turned_over_lr' =>
                ['nullable', 'date'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Disposal is its own action
        |--------------------------------------------------------------------------
        |
        | Marking a case disposed from here would create a closed case with no
        | Date Disposed, which is exactly the state that breaks the 30-day PCT.
        |
        */

        if (
            $data['status'] === Workflow::DISPOSED
            && $rfa->date_disposed === null
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Record the disposition below instead — a case cannot be marked disposed without a Date Disposed.',
            ]);
        }

        $this->guardChronology($rfa, $data);

        $this->workflow->apply(
            $rfa,
            $data,
            $request->user(),
            'workflow',
            'Workflow progress updated'
        );

        return $this->back(
            $rfa,
            'Workflow progress saved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Conference Progression
    |--------------------------------------------------------------------------
    */

    public function updateConference(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $data = $request->validate([
            'date_initial_conference' =>
                ['nullable', 'date'],

            'date_second_conference' =>
                ['nullable', 'date'],

            'date_both_parties_appeared' =>
                ['nullable', 'date'],
        ]);

        /*
        | A second conference only makes sense after a first one.
        */

        if (
            ! empty($data['date_second_conference'])
            && empty($data['date_initial_conference'])
        ) {
            throw ValidationException::withMessages([
                'date_second_conference' =>
                    'Record the 1st Conference date before the 2nd Conference date.',
            ]);
        }

        $this->guardChronology($rfa, $data);

        $this->workflow->apply(
            $rfa,
            $data,
            $request->user(),
            'conference',
            'Conference progression updated'
        );

        return $this->back(
            $rfa,
            'Conference details saved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Disposition Capture
    |--------------------------------------------------------------------------
    */

    public function updateDisposition(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $data = $request->validate([
            'disposition_status' =>
                ['nullable', 'string', 'max:50'],

            'disposition_mode' =>
                ['nullable', 'string', 'max:50'],

            'date_disposed' =>
                ['nullable', 'date'],

            'monetary_benefit' =>
                ['nullable', 'numeric', 'min:0', 'max:999999999999'],

            'workers_benefited' =>
                ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | A disposition is a status and a date together
        |--------------------------------------------------------------------------
        */

        $hasStatus = ! empty($data['disposition_status']);

        $hasDate = ! empty($data['date_disposed']);

        if ($hasDate && ! $hasStatus) {
            throw ValidationException::withMessages([
                'disposition_status' =>
                    'Select the official disposition that closes this case.',
            ]);
        }

        if ($hasStatus && ! $hasDate) {
            throw ValidationException::withMessages([
                'date_disposed' =>
                    'Record the date the case was disposed.',
            ]);
        }

        if ($hasDate) {
            $data['status'] = Workflow::DISPOSED;
        }

        $this->guardChronology($rfa, $data);

        $this->workflow->apply(
            $rfa,
            $data,
            $request->user(),
            'disposition',
            $hasDate
                ? 'Case disposed'
                : 'Disposition details updated'
        );

        return $this->back(
            $rfa,
            'Disposition saved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Reopen
    |--------------------------------------------------------------------------
    */

    public function reopen(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $request->validate([
            'reason' =>
                ['required', 'string', 'max:1000'],
        ]);

        if (! $rfa->isDisposed()) {
            throw ValidationException::withMessages([
                'reason' =>
                    'This case is not currently disposed.',
            ]);
        }

        $this->workflow->apply(
            $rfa,
            [
                'date_disposed' => null,

                'disposition_status' => null,

                'status' => Workflow::FOR_DISPOSITION,
            ],
            $request->user(),
            'reopened',
            'Case reopened',
            $request->string('reason')->toString()
        );

        return $this->back(
            $rfa,
            'Case reopened. The previous disposition is preserved in the timeline.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Timeline Note
    |--------------------------------------------------------------------------
    */

    public function storeNote(
        Request $request,
        Rfa $rfa
    ): RedirectResponse {
        $validated = $request->validate([
            'note' =>
                ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->log(
            $rfa,
            $request->user(),
            'note',
            'Note added',
            $validated['note']
        );

        return $this->back(
            $rfa,
            'Note added to the case timeline.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Reject a change that would introduce a NEW chronology problem.
     *
     * Problems already present in imported data are left alone — they are
     * surfaced on the page instead, so a case officer can correct them
     * deliberately rather than being blocked by history.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function guardChronology(
        Rfa $rfa,
        array $attributes
    ): void {
        $existing = $this->workflow->chronologyIssues($rfa);

        $candidate = clone $rfa;

        $candidate->fill($attributes);

        $introduced = array_values(
            array_diff(
                $this->workflow->chronologyIssues($candidate),
                $existing
            )
        );

        if ($introduced === []) {
            return;
        }

        throw ValidationException::withMessages([
            'chronology' => $introduced,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAssignee(
        ?int $userId,
        ?string $typedName,
        string $idField,
        string $nameField
    ): array {
        if ($userId !== null) {
            $user = User::query()->find($userId);

            if ($user !== null) {
                return [
                    $idField => $user->id,

                    $nameField => $user->name,
                ];
            }
        }

        return [
            $idField => null,

            $nameField => $typedName !== null && trim($typedName) !== ''
                ? trim($typedName)
                : null,
        ];
    }

    /**
     * Distinct non-empty values already present in the data, so dropdowns
     * offer real operational vocabulary instead of invented options.
     *
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        return Rfa::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    private function back(
        Rfa $rfa,
        string $message
    ): RedirectResponse {
        return redirect()
            ->route('rfas.show', $rfa)
            ->with('status', $message);
    }
}
