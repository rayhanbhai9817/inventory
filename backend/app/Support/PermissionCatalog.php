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
            'brands' => ['view', 'create', 'edit', 'delete'],
            'units' => ['view', 'create', 'edit', 'delete'],
            'warehouses' => ['view', 'create', 'edit', 'delete'],
            'inventory' => ['view', 'adjust', 'transfer'],
            'suppliers' => ['view', 'create', 'edit', 'delete'],
            'customers' => ['view', 'create', 'edit', 'delete'],
            'purchases' => ['view', 'create', 'edit', 'delete', 'approve'],
            'purchase_returns' => ['view', 'create', 'delete'],
            'sales' => ['view', 'create', 'edit', 'delete'],
            'sales_returns' => ['view', 'create', 'delete'],
            'payments' => ['view', 'create', 'delete'],
            'expenses' => ['view', 'create', 'edit', 'delete'],
            'reports' => ['view', 'export'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view', 'create', 'edit', 'delete'],
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
                'products.*', 'categories.*', 'brands.*', 'units.*', 'warehouses.*',
                'inventory.*',
                'suppliers.*', 'customers.*',
                'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.approve',
                'purchase_returns.*',
                'sales.view', 'sales.create', 'sales.edit',
                'sales_returns.*',
                'payments.view', 'payments.create',
                'expenses.*',
                'reports.*',
            ],

            'Staff' => [
                'dashboard.view',
                'products.view',
                'customers.view', 'customers.create',
                'sales.view', 'sales.create',
                'payments.view', 'payments.create',
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
