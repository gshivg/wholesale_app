<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('role:packaging,super_admin'). Super admins always pass. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);
        if (! $user->isSuperAdmin()) {
            abort_unless(in_array($user->role->value, $roles, true), 403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
