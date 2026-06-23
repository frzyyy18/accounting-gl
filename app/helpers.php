<?php

if (! function_exists('rupiah')) {
    function rupiah(float|int|string|null $value, bool $withPrefix = true): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        $formatted = number_format($amount, 2, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }
}

if (! function_exists('corporateTaxRate')) {
    function corporateTaxRate(bool $refresh = false): float
    {
        static $rate = null;

        if ($refresh || $rate === null) {
            $rate = (float) (\App\Models\SystemSetting::where('key', 'tax_rate_corporate')->value('value') ?? 22);
        }

        return $rate / 100;
    }
}
