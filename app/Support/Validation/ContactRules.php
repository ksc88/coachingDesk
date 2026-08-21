<?php

namespace App\Support\Validation;

class ContactRules
{
    /**
     * Indian mobile: 10 digits starting 6–9.
     * Accepts optional +91 / 91 / spaces / dashes.
     *
     * @return list<string|\Closure>
     */
    public static function indianMobile(bool $required = true): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! self::isValidIndianMobile((string) $value)) {
                    $fail('Enter a valid 10-digit Indian mobile number (starts with 6–9).');
                }
            },
        ];

        return $rules;
    }

    public static function isValidIndianMobile(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return (bool) preg_match('/^[6-9]\d{9}$/', $digits);
    }

    public static function normalizeIndianMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Brevo (and similar SMS APIs) expect digits with country code, no +.
     */
    public static function toSmsRecipient(string $value, string $countryCode = '91'): string
    {
        $local = self::normalizeIndianMobile($value);

        if ($local === '' || ! preg_match('/^[6-9]\d{9}$/', $local)) {
            return '';
        }

        return $countryCode.$local;
    }

    /**
     * @return list<string>
     */
    public static function personName(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:2',
            'max:120',
            'regex:/^[\pL\s.\'-]+$/u',
        ];
    }
}
