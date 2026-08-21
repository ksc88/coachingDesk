<?php

namespace App\Domain\Identity;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::query()->create([
            'tenant_id' => TenantContext::id() ?? Auth::user()?->tenant_id,
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
        ]);
    }
}
