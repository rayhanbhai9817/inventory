<?php

namespace App\Support;

/**
 * Single source of truth for every permission name and the default role
 * templates granted to a newly provisioned business. Permissions are
 * global reference data (shared across tenants); roles are created
 * per-tenant by App\Services\TenantProvisioningService using this catalog.
 */
class PermissionCatalog
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $modules = [
            'dashboard' => ['view'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'categories' => ['view', 'create', 'edit', 'delete'],
            'inventory' => ['view'],
            'batches' => ['view'],
            'stock' => ['in', 'out', 'adjust'],
            'ledger' => ['view'],
            'activity' => ['view'],
            'audit' => ['view'],
            'reports' => ['view', 'export'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view'],
            'settings' => ['manage'],
        ];

        $permissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * Default role => permission-name-pattern map used when provisioning a
     * new business. 'Owner' always receives every permission.
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultRoles(): array
    {
        return [
            'Owner' => ['*'],

            'Manager' => [
                'dashboard.view',
                'products.*', 'categories.*',
                'inventory.view', 'batches.view',
                'stock.in', 'stock.out', 'stock.adjust',
                'ledger.view', 'activity.view',
                'reports.*',
            ],

            'Staff' => [
                'dashboard.view',
                'products.view', 'categories.view',
                'inventory.view', 'batches.view',
                'stock.in', 'stock.out',
                'ledger.view',
            ],
        ];
    }

    /**
     * Expand '*' wildcards (module.* and the global '*') against the full
     * permission list.
     *
     * @param  array<int, string>  $patterns
     * @return array<int, string>
     */
    public static function expand(array $patterns): array
    {
        $all = self::all();

        if (in_array('*', $patterns, true)) {
            return $all;
        }

        $resolved = [];
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1); // keep trailing dot
                foreach ($all as $permission) {
                    if (str_starts_with($permission, $prefix)) {
                        $resolved[] = $permission;
                    }
                }
            } else {
                $resolved[] = $pattern;
            }
        }

        return array_values(array_unique($resolved));
    }
}
