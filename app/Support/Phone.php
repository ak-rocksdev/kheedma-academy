<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise an Indonesian phone number to canonical +62 form.
     *
     *   08xx...  -> +628xx...
     *   62xx...  -> +6262? no — 62... -> +62...
     *   +62xx... -> unchanged
     *   8xx...   -> +628xx...
     *
     * Returns null for empty input; returns the cleaned string unchanged when it
     * does not look Indonesian (validation will then reject it).
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Keep digits and a leading +, drop spaces / dashes / dots / parentheses.
        $value = preg_replace('/[^\d+]/', '', $raw);
        $value = preg_replace('/(?!^)\+/', '', $value); // strip any non-leading '+'

        if ($value === '' || $value === '+') {
            return null;
        }

        if (str_starts_with($value, '+62')) {
            return $value;
        }
        if (str_starts_with($value, '62')) {
            return '+'.$value;
        }
        if (str_starts_with($value, '0')) {
            return '+62'.substr($value, 1);
        }
        if (str_starts_with($value, '8')) {
            return '+62'.$value;
        }

        return $value;
    }
}
