<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'business_id' => Tenant::id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->assignRole($data['role']);

        $this->auditLogger->log('user_created', $user, null, ['email' => $user->email, 'role' => $data['role']], $request->user());

        return response()->json(['data' => new UserResource($user->load('roles'))], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }
        $user->syncRoles([$data['role']]);

        $this->auditLogger->log('user_updated', $user, null, ['email' => $user->email, 'role' => $data['role']], $request->user());

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    public function setStatus(Request $request, User $user): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:active,inactive']]);

        if ($user->id === $request->user()->id && $request->string('status') === 'inactive') {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['status' => $request->string('status')->toString()]);
        $this->auditLogger->log('user_status_changed', $user, null, ['status' => $user->status], $request->user());

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate(['password' => ['required', Password::defaults()]]);

        $user->update(['password' => $request->string('password')->toString()]);
        $this->auditLogger->log('user_password_reset', $user, null, null, $request->user());

        return response()->json(['message' => 'Password reset.']);
    }
}
