<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Read-only: the three role templates (Owner/Manager/Staff) are
     * provisioned automatically per business at registration
     * (TenantProvisioningService) with a fixed permission set. There is
     * no custom-role-builder UI in this phase — see docs/PROGRESS.md.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]),
        ]);
    }
}
