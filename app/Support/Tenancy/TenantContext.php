<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

class TenantContext
{
    protected static ?int $tenantId = null;

    protected static ?Tenant $tenant = null;

    public static function set(?Tenant $tenant): void
    {
        static::$tenant = $tenant;
        static::$tenantId = $tenant?->id;
    }

    public static function setId(?int $tenantId): void
    {
        static::$tenantId = $tenantId;
        static::$tenant = null;
    }

    public static function id(): ?int
    {
        return static::$tenantId;
    }

    public static function tenant(): ?Tenant
    {
        if (static::$tenant) {
            return static::$tenant;
        }

        if (! static::$tenantId) {
            return null;
        }

        return static::$tenant = Tenant::query()->find(static::$tenantId);
    }

    public static function clear(): void
    {
        static::$tenant = null;
        static::$tenantId = null;
    }

    public static function run(int $tenantId, callable $callback): mixed
    {
        $previousId = static::$tenantId;
        $previous = static::$tenant;

        static::setId($tenantId);

        try {
            return $callback();
        } finally {
            static::$tenantId = $previousId;
            static::$tenant = $previous;
        }
    }
}
