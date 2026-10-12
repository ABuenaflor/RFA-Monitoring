<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Services\PctCalendar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * My Conference Schedule: one week of the signed-in user's own 1st and
 * 2nd conferences. There is no way to ask for another user's schedule —
 * the cases are always those handled by the person viewing the page.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, PctCalendar $calendar): View
    {
        $user = $request->user();

        try {
            $anchor = Carbon::parse((string) $request->query('week', 'today'));
        } catch (\Throwable) {
            $anchor = Carbon::today();
        }

        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $weekEnd = $weekStart->copy()->addDays(6);

        $rfas = Rfa::query()
            ->handledBySeado($user)
            ->where(fn (Builder $query) => $query
                ->whereBetween('date_initial_conference', [$weekStart->toDateString(), $weekEnd->toDateString() . ' 23:59:59'])
                ->orWhereBetween('date_second_conference', [$weekStart->toDateString(), $weekEnd->toDateString() . ' 23:59:59']))
            ->orderBy('docket_no')
            ->get();

        /*
        | One entry per conference: a case with both conferences in the same
        | week appears on both days.
        */

        $entries = collect();

        foreach ($rfas as $rfa) {
            foreach ([
                'date_initial_conference' => '1st Conference',
                'date_second_conference' => '2nd Conference',
            ] as $field => $label) {
                $date = $rfa->{$field};

                if ($date && $date->betweenIncluded($weekStart, $weekEnd->copy()->endOfDay())) {
                    $entries->push([
                        'date' => $date->format('Y-m-d'),
                        'label' => $label,
                        'rfa' => $rfa,
                    ]);
                }
            }
        }

        $byDay = $entries->groupBy('date');

        /*
        | Monday to Friday always; a weekend day only when something is on it.
        */

        $days = collect(range(0, 6))
            ->map(fn (int $offset) => $weekStart->copy()->addDays($offset))
            ->filter(fn (Carbon $day) => ! $day->isWeekend() || $byDay->has($day->format('Y-m-d')))
            ->map(fn (Carbon $day) => [
                'date' => $day,
                'holiday' => $calendar->holidayName($day),
                'entries' => $byDay->get($day->format('Y-m-d'), collect())->values(),
            ])
            ->values();

        return view('schedule.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'total' => $entries->count(),
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'isCurrentWeek' => $weekStart->isSameDay(Carbon::today()->startOfWeek(Carbon::MONDAY)),
            'upcoming' => $this->nextConference($user, $weekEnd),
            'caseCount' => Rfa::query()->handledBySeado($user)->count(),
        ]);
    }

    /**
     * The first conference after this week, so an empty week still points
     * somewhere useful.
     *
     * @return array{date: Carbon, label: string, rfa: Rfa}|null
     */
    private function nextConference($user, Carbon $after): ?array
    {
        $after = $after->toDateString() . ' 23:59:59';

        $candidates = new Collection();

        foreach (['date_initial_conference' => '1st Conference', 'date_second_conference' => '2nd Conference'] as $field => $label) {
            $rfa = Rfa::query()
                ->handledBySeado($user)
                ->where($field, '>', $after)
                ->orderBy($field)
                ->first();

            if ($rfa) {
                $candidates->push(['date' => $rfa->{$field}, 'label' => $label, 'rfa' => $rfa]);
            }
        }

        return $candidates->sortBy(fn (array $candidate) => $candidate['date']->timestamp)->first();
    }
}
