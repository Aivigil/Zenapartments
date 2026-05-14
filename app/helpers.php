<?php

if (! function_exists('money_minor_to_major')) {
    /**
     * Convert minor units (paisa) to major (rupee) as float.
     * Money is always stored in minor units; never use floats in the DB layer.
     */
    function money_minor_to_major(int $minor): float
    {
        return $minor / 100;
    }
}

if (! function_exists('money_major_to_minor')) {
    /**
     * Convert a major-unit value (e.g. user input "1,200.50") to minor units.
     * Trims commas and whitespace; rounds half-up.
     */
    function money_major_to_minor(string|float|int $major): int
    {
        if (is_string($major)) {
            $major = str_replace([',', ' '], '', trim($major));
            $major = (float) $major;
        }
        return (int) round(((float) $major) * 100);
    }
}

if (! function_exists('money_format_pkr')) {
    function money_format_pkr(int $minor, bool $withSymbol = true): string
    {
        $major = $minor / 100;
        $formatted = number_format($major, 2, '.', ',');
        return $withSymbol ? "Rs. {$formatted}" : $formatted;
    }
}

if (! function_exists('cnic_hash')) {
    /**
     * Stable HMAC of a CNIC for dedupe lookups (never store plaintext).
     */
    function cnic_hash(string $cnic): string
    {
        $digits = preg_replace('/\D/', '', $cnic);
        return hash_hmac('sha256', $digits, config('app.key'));
    }
}

if (! function_exists('generate_code')) {
    /**
     * Generate a zero-padded sequence-based code: e.g. generate_code('ZR-C-', 12, 5) → "ZR-C-00012"
     */
    function generate_code(string $prefix, int $sequence, int $pad = 5): string
    {
        return $prefix . str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);
    }
}
