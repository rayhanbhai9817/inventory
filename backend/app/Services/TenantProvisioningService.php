<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets up a newly registered business: its default role templates
 * (Owner/Manager/Staff), scoped to the business as a Spatie "team", and
 * assigns the Owner role to the user who registered it.
 */
class TenantProvisioningService
{
    public function provision(Business $business, User $owner): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($business->id);

        foreach (PermissionCatalog::defaultRoles() as $roleName => $patterns) {
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => $business->id,
            ]);

            $permissionNames = PermissionCatalog::expand($patterns);
            $permissions = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->get();

            $role->syncPermissions($permissions);
        }

        $owner->assignRole('Owner');
    }
}
