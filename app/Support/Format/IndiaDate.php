<?php

namespace App\Support\Format;

use Carbon\Carbon;
use DateTimeInterface;

class IndiaDate
{
    public static function format(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::pattern());
        }

        return Carbon::parse($value)->format(self::pattern());
    }

    public static function display(DateTimeInterface|string|null $value, string $empty = '—'): string
    {
        return self::format($value) ?? $empty;
    }

    public static function pattern(): string
    {
        return (string) config('coaching.display_date_format', 'd-m-Y');
    }

    /** Parse user-facing or CSV date into Y-m-d for storage. */
    public static function toStorage(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $value)) {
            return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
