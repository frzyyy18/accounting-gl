<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $allowed = $user && collect($permissions)->contains(fn ($permission) => $this->allows($user, $permission));

        if ($allowed && ! $request->isMethodSafe() && $user->hasRole('auditor') && ! in_array($request->route()?->getName(), ['logout', 'password.change'], true)) {
            AuditLog::record('access_denied', 'Permission', null, [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'reason' => 'read_only_role_write_attempt',
            ]);

            abort(403, 'Role ini hanya boleh membaca data.');
        }

        if (! $allowed) {
            if ($user) {
                AuditLog::record('access_denied', 'Permission', null, [
                    'route' => $request->route()?->getName(),
                    'permissions' => $permissions,
                    'path' => $request->path(),
                ]);
            }

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        return $next($request);
    }

    private function allows($user, string $permission): bool
    {
        if ($permission === 'app.access') {
            return collect(Permission::catalog())
                ->flatten(1)
                ->pluck(0)
                ->contains(fn (string $code) => $user->canManage($code));
        }

        return $user->canManage($permission);
    }
}
