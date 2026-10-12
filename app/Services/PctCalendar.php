<?php

namespace App\Services;

use App\Models\Holiday;
use App\Support\SystemSettings;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * How the PCT clock counts days.
 *
 *  - working  (default) Monday to Friday only, skipping every holiday or
 *             event an administrator has entered under Holidays & Events
 *  - calendar every day counts, weekends and holidays included
 *
 * The mode is the "PCT Day Counting" system setting. The start day is
 * always day 0; day 1 is the next day that counts.
 */
class PctCalendar
{
    public const MODE_WORKING = 'working';

    public const MODE_CALENDAR = 'calendar';

    /**
     * Holiday dates that fall on a weekday, as sorted day numbers.
     *
     * @var array<int, int>
     */
    private array $holidayDays = [];

    /**
     * The same holidays as a lookup set.
     *
     * @var array<int, true>
     */
    private array $holidaySet = [];

    /**
     * @var array<string, string>  Y-m-d => name
     */
    private array $holidayNames = [];

    /**
     * @param  array<string, string>  $holidays  Y-m-d => name
     */
    public function __construct(
        private readonly string $mode = self::MODE_WORKING,
        array $holidays = []
    ) {
        foreach ($holidays as $date => $name) {
            $date = substr((string) $date, 0, 10);

            $this->holidayNames[$date] = (string) $name;

            $day = self::dayNumber(Carbon::parse($date));

            if (self::isWeekdayNumber($day)) {
                $this->holidayDays[] = $day;
            }
        }

        $this->holidayDays = array_values(array_unique($this->holidayDays));

        sort($this->holidayDays);

        $this->holidaySet = array_fill_keys($this->holidayDays, true);
    }

    /**
     * The calendar as configured: the system setting plus the holidays table.
     *
     * Falls back to working days with no holidays when the tables do not
     * exist yet (before `php artisan migrate`), so PCT pages never break.
     */
    public static function fromDatabase(SettingsService $settings): self
    {
        try {
            $mode = (string) $settings->get(SystemSettings::PCT_DAY_COUNTING);
        } catch (QueryException) {
            $mode = self::MODE_WORKING;
        }

        try {
            $holidays = Holiday::query()
                ->orderBy('date')
                ->get(['date', 'name'])
                ->mapWithKeys(fn (Holiday $holiday) => [
                    $holiday->date->format('Y-m-d') => $holiday->name,
                ])
                ->all();
        } catch (QueryException) {
            $holidays = [];
        }

        return new self(
            in_array($mode, [self::MODE_WORKING, self::MODE_CALENDAR], true)
                ? $mode
                : self::MODE_WORKING,
            $holidays
        );
    }

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_WORKING => 'Working days (Mon–Fri, skipping holidays & events)',
            self::MODE_CALENDAR => 'Calendar days (every day counts)',
        ];
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function countsWorkingDays(): bool
    {
        return $this->mode === self::MODE_WORKING;
    }

    /**
     * Short phrase for page footnotes, e.g. "working days".
     */
    public function unitLabel(): string
    {
        return $this->countsWorkingDays()
            ? 'working days (Monday to Friday, excluding holidays and events)'
            : 'calendar days';
    }

    /**
     * Days from $start to $end that count, with $start as day 0.
     *
     * Negative when $end is earlier than $start, so callers can still flag
     * the dates as invalid.
     */
    public function daysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $from = self::dayNumber($start);

        $to = self::dayNumber($end);

        if (! $this->countsWorkingDays()) {
            return $to - $from;
        }

        if ($to < $from) {
            return -max(1, $this->workingDaysIn($to, $from));
        }

        return $this->workingDaysIn($from, $to);
    }

    /**
     * The date $days counted days after $start (the deadline for a limit).
     */
    public function addDays(CarbonInterface $start, int $days): Carbon
    {
        if (! $this->countsWorkingDays()) {
            return Carbon::parse($start->format('Y-m-d'))->addDays($days);
        }

        $day = self::dayNumber($start);

        $counted = 0;

        while ($counted < $days) {
            $day++;

            if (self::isWeekdayNumber($day) && ! isset($this->holidaySet[$day])) {
                $counted++;
            }
        }

        return Carbon::parse(gmdate('Y-m-d', $day * 86400));
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        if (! $this->countsWorkingDays()) {
            return true;
        }

        $day = self::dayNumber($date);

        return self::isWeekdayNumber($day)
            && ! $this->isHolidayNumber($day);
    }

    public function holidayName(CarbonInterface $date): ?string
    {
        return $this->holidayNames[$date->format('Y-m-d')] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Arithmetic
    |--------------------------------------------------------------------------
    |
    | Dates are turned into whole day numbers (days since 1970-01-01) so
    | weekdays can be counted with a formula instead of walking every day:
    | the PCT pages evaluate thousands of checkpoints per request.
    |
    */

    /**
     * Counting days in ($from, $to]: weekdays minus weekday holidays.
     */
    private function workingDaysIn(int $from, int $to): int
    {
        return self::weekdaysUpTo($to) - self::weekdaysUpTo($from)
            - ($this->holidaysUpTo($to) - $this->holidaysUpTo($from));
    }

    /**
     * Weekdays from Monday 1969-12-29 (day -3) up to and including $day.
     */
    private static function weekdaysUpTo(int $day): int
    {
        $span = $day + 4;

        return intdiv($span, 7) * 5 + min($span % 7, 5);
    }

    /**
     * Weekday holidays on or before $day.
     */
    private function holidaysUpTo(int $day): int
    {
        $low = 0;

        $high = count($this->holidayDays);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->holidayDays[$middle] <= $day) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    private function isHolidayNumber(int $day): bool
    {
        return isset($this->holidaySet[$day]);
    }

    private static function isWeekdayNumber(int $day): bool
    {
        // 1970-01-01 (day 0) was a Thursday; Monday = 0 … Sunday = 6.
        return ((($day + 3) % 7) + 7) % 7 < 5;
    }

    private static function dayNumber(CarbonInterface $date): int
    {
        return intdiv(
            (int) strtotime($date->format('Y-m-d') . ' 00:00:00 UTC'),
            86400
        );
    }
}
