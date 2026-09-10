<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Support\PermissionCatalog;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent, safe to run anytime (including repeatedly against
 * production). `TenantProvisioningService::provision()` only runs once,
 * at business creation — when PermissionCatalog gains new permissions
 * or a role's default pattern changes (as it did when the Supplier and
 * Product Pricing modules were added), already-provisioned businesses'
 * roles don't automatically pick them up. This re-syncs every business's
 * Owner/Manager/Staff roles against the current catalog: creates a role
 * if a business somehow doesn't have it yet, and re-syncs permissions
 * for roles that do — it never removes a business's own custom
 * assignments outside these three fixed templates, because there are
 * none (no custom-role builder exists — see docs/PROGRESS.md).
 */
class SyncRolePermissionsCommand extends Command
{
    protected $signature = 'app:sync-role-permissions';

    protected $description = 'Re-sync every business\'s Owner/Manager/Staff role permissions with the current permission catalog';

    public function handle(): int
    {
        $businesses = Business::all(['id', 'name']);

        if ($businesses->isEmpty()) {
            $this->info('No businesses exist yet — nothing to sync.');

            return self::SUCCESS;
        }

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        $this->withProgressBar($businesses, function ($business) use ($registrar) {
            DB::transaction(function () use ($business, $registrar) {
                Tenant::set($business->id);
                $registrar->setPermissionsTeamId($business->id);

                foreach (PermissionCatalog::defaultRoles() as $roleName => $patterns) {
                    $role = Role::firstOrCreate(
                        ['name' => $roleName, 'guard_name' => 'web', 'team_id' => $business->id],
                    );

                    $permissionNames = PermissionCatalog::expand($patterns);
                    $permissions = Permission::whereIn('name', $permissionNames)
                        ->where('guard_name', 'web')
                        ->get();

                    $role->syncPermissions($permissions);
                }
            });
        });

        $this->newLine(2);
        $this->info("Synced role permissions for {$businesses->count()} business(es).");

        return self::SUCCESS;
    }
}
