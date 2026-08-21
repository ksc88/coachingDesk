<?php

namespace App\Domain\Academics;

class BatchScheduleFormatter
{
    /** @var array<int, string> ISO-8601: 1 = Monday … 7 = Sunday */
    public const WEEKDAY_LABELS = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    public const SHIFTS = ['morning', 'afternoon', 'evening', 'custom'];

    /**
     * @param  list<int|string>|null  $weekdays
     */
    public function format(?array $weekdays, ?string $startsAt, ?string $endsAt, ?string $shift): ?string
    {
        $parts = array_filter([
            $this->formatWeekdays($weekdays),
            $this->formatTimeRange($startsAt, $endsAt),
            $this->formatShift($shift),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  list<int|string>|null  $weekdays
     */
    public function formatWeekdays(?array $weekdays): ?string
    {
        $days = collect($weekdays ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => isset(self::WEEKDAY_LABELS[$day]))
            ->unique()
            ->sort()
            ->values();

        if ($days->isEmpty()) {
            return null;
        }

        // Collapse consecutive ranges: Mon–Sat, Mon/Wed/Fri, etc.
        $labels = $days->map(fn (int $day) => self::WEEKDAY_LABELS[$day])->all();
        $ints = $days->all();

        if (count($ints) >= 3 && $this->isConsecutive($ints)) {
            return $labels[0].'–'.$labels[array_key_last($labels)];
        }

        return implode('/', $labels);
    }

    public function formatTimeRange(?string $startsAt, ?string $endsAt): ?string
    {
        $start = $this->normalizeTime($startsAt);
        $end = $this->normalizeTime($endsAt);

        if ($start && $end) {
            return $start.'–'.$end;
        }

        return $start ?: $end;
    }

    public function formatShift(?string $shift): ?string
    {
        $shift = strtolower(trim((string) $shift));

        return match ($shift) {
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
            'evening' => 'Evening',
            'custom' => null,
            default => null,
        };
    }

    public function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return null;
        }

        // Accept HH:MM or HH:MM:SS
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    /**
     * @param  list<int>  $days
     */
    protected function isConsecutive(array $days): bool
    {
        for ($i = 1; $i < count($days); $i++) {
            if ($days[$i] !== $days[$i - 1] + 1) {
                return false;
            }
        }

        return true;
    }
}
