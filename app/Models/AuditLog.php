<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'branch_id', 'action', 'module', 'old_value', 'new_value',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public static function record(string $action, string $module, mixed $old = null, mixed $new = null, ?int $companyId = null, ?int $branchId = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'company_id' => $companyId ?? auth()->user()?->company_id,
            'branch_id' => $branchId,
            'action' => $action,
            'module' => $module,
            'old_value' => static::redactSensitive($old),
            'new_value' => static::redactSensitive($new),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private static function redactSensitive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sensitiveKeys = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token'];

        foreach ($value as $key => $item) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                $value[$key] = '[REDACTED]';

                continue;
            }

            $value[$key] = static::redactSensitive($item);
        }

        return $value;
    }
}
