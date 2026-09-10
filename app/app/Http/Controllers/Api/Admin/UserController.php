<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telemetry\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at']);

        return response()->json(['data' => $users]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string', Rule::in(config('telemetry.roles'))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $previousRole = $user->role;
        $user->fill($validated);
        $user->save();

        if (array_key_exists('role', $validated) && $validated['role'] !== $previousRole) {
            $this->audit->logFromRequest(
                $request,
                'user.role_changed',
                'user',
                (string) $user->id,
                [
                    'previous_role' => $previousRole,
                    'new_role' => $validated['role'],
                ],
            );
        }

        return response()->json(['data' => $user->fresh()]);
    }
}
