<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id && ! $user->is_platform_admin) {
            TenantContext::setId((int) $user->tenant_id);
        }

        if ($request->header('X-Tenant-Id') && $user?->is_platform_admin) {
            TenantContext::setId((int) $request->header('X-Tenant-Id'));
        }

        return $next($request);
    }
}
