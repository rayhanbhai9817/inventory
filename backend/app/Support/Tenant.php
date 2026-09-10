<?php

namespace App\Support;

/**
 * Holds the current tenant (business) id for the lifetime of a request,
 * console command, or test. Set exclusively by
 * App\Http\Middleware\EnsureTenantContext for HTTP requests — never trust
 * a tenant id that arrived from client input (URL, body, header).
 */
class Tenant
{
    private static ?int $businessId = null;

    public static function id(): ?int
    {
        return self::$businessId;
    }

    public static function set(?int $businessId): void
    {
        self::$businessId = $businessId;
    }

    public static function check(): bool
    {
        return self::$businessId !== null;
    }

    public static function forget(): void
    {
        self::$businessId = null;
    }
}
