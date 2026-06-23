<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

class TransactionAnomaly
{
    public static function recordIfNeeded(string $module, array $context, float $amount, ?int $companyId = null, ?int $branchId = null): void
    {
        $reasons = [];
        $hour = Carbon::now()->hour;
        $startHour = (int) env('TRANSACTION_ANOMALY_START_HOUR', 8);
        $endHour = (int) env('TRANSACTION_ANOMALY_END_HOUR', 18);
        $threshold = (float) env('TRANSACTION_ANOMALY_AMOUNT', 100000000);

        if ($amount >= $threshold) {
            $reasons[] = 'large_amount';
        }

        if ($hour < $startHour || $hour >= $endHour) {
            $reasons[] = 'outside_business_hours';
        }

        if ($reasons === []) {
            return;
        }

        AuditLog::record('transaction_anomaly', $module, null, $context + [
            'amount' => $amount,
            'reasons' => $reasons,
        ], $companyId, $branchId);
    }
}
