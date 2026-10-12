<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\PctCalendar;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Holidays & Events: the non-working dates the PCT clock skips when it
 * counts working days. Anyone who can see PCT may view the list; only
 * holidays.manage may change it.
 */
class HolidayController extends Controller
{
    /**
     * A single entry may cover a multi-day event, but not more than this.
     */
    private const MAX_RANGE_DAYS = 31;

    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);

        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $editing = $request->user()->can('holidays.manage') && $request->filled('edit')
            ? Holiday::query()->find($request->integer('edit'))
            : null;

        $years = Holiday::query()
            ->pluck('date')
            ->map(fn ($date) => (int) Carbon::parse($date)->year)
            ->push(now()->year, now()->year + 1, $year)
            ->unique()
            ->sort()
            ->values();

        return view('holidays.index', [
            'year' => $year,
            'years' => $years,
            'holidays' => $holidays,
            'byMonth' => $holidays->groupBy(fn (Holiday $holiday) => $holiday->date->format('F')),
            'editing' => $editing,
            'types' => Holiday::types(),
            'countsWorkingDays' => app(PctCalendar::class)->countsWorkingDays(),
            'weekdayCount' => $holidays->filter(fn (Holiday $holiday) => ! $holiday->date->isWeekend())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Holiday::types()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['date'])->startOfDay();

        $end = isset($data['end_date'])
            ? Carbon::parse($data['end_date'])->startOfDay()
            : $start->copy();

        if ($start->diffInDays($end) >= self::MAX_RANGE_DAYS) {
            return back()
                ->withInput()
                ->withErrors(['end_date' => 'A single entry can cover at most ' . self::MAX_RANGE_DAYS . ' days.']);
        }

        $taken = Holiday::query()
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $added = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (in_array($date->format('Y-m-d'), $taken, true)) {
                continue;
            }

            Holiday::create([
                'date' => $date->format('Y-m-d'),
                'name' => $data['name'],
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $added++;
        }

        if ($added === 0) {
            return back()
                ->withInput()
                ->withErrors(['date' => 'That date already has a holiday or event. Edit the existing entry instead.']);
        }

        $this->refreshCalendar();

        $skipped = count($taken);

        return redirect()
            ->route('holidays.index', ['year' => $start->year])
            ->with('status', $added . ' ' . str('date')->plural($added) . ' added.'
                . ($skipped > 0 ? ' ' . $skipped . ' already listed ' . str('date')->plural($skipped) . ' were left unchanged.' : '')
                . ' PCT ratings now skip them.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $request->validate([
            'date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($holiday) {
                    $clash = Holiday::query()
                        ->whereDate('date', Carbon::parse($value))
                        ->whereKeyNot($holiday->id)
                        ->exists();

                    if ($clash) {
                        $fail('Another holiday or event is already on that date.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Holiday::types()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $holiday->update($data + ['updated_by' => $request->user()->id]);

        $this->refreshCalendar();

        return redirect()
            ->route('holidays.index', ['year' => $holiday->date->year])
            ->with('status', 'Holiday / event updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $year = $holiday->date->year;

        $holiday->delete();

        $this->refreshCalendar();

        return redirect()
            ->route('holidays.index', ['year' => $year])
            ->with('status', 'Holiday / event removed. PCT ratings count that date again.');
    }

    /**
     * Drop this request's cached calendar so anything evaluated after the
     * change sees it.
     */
    private function refreshCalendar(): void
    {
        app()->forgetInstance(PctCalendar::class);
    }
}
