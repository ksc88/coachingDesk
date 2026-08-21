<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->is_platform_admin) {
            // Platform admins only see tenant data when they explicitly name a tenant.
            if ($request->hasHeader('X-Tenant-Id') && TenantContext::id()) {
                return $next($request);
            }

            return redirect()->route('platform.coachings.index');
        }

        if (! $user->tenant_id || ! $user->is_active) {
            abort(403, 'No active coaching organization assigned.');
        }

        if ($user->tenant && $user->tenant->status !== 'active') {
            abort(403, 'This coaching account is suspended. Please contact your service provider.');
        }

        return $next($request);
    }
}
