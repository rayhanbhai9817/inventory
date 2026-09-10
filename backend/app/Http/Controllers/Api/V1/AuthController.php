<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Support\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * Register a new business (tenant) and its owner user in one step.
     * This is the only endpoint that may create a business without an
     * existing authenticated tenant context.
     */
    public function register(RegisterBusinessRequest $request, TenantProvisioningService $provisioner): JsonResponse
    {
        $data = $request->validated();

        [$business, $user] = DB::transaction(function () use ($data, $provisioner) {
            $business = Business::create([
                'name' => $data['business_name'],
                'slug' => Business::uniqueSlug($data['business_name']),
            ]);

            Tenant::set($business->id);

            $user = User::create([
                'business_id' => $business->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $provisioner->provision($business, $user);

            return [$business, $user];
        });

        event(new Registered($user));

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'business' => new BusinessResource($business),
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Contact your administrator.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        // /auth/login is intentionally outside the 'tenant' middleware
        // group (the user isn't authenticated yet when it runs), so the
        // tenant/team context that scopes role lookups is never set by
        // that middleware here. Set it explicitly so the response's
        // roles/permissions are correct instead of always empty.
        Tenant::set($user->business_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->business_id);

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('roles')),
            'business' => new BusinessResource($request->user()->business),
        ]);
    }
}
