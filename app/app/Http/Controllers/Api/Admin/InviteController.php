<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\UserInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InviteController extends Controller
{
    public function __construct(
        protected UserInviteService $invites,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->invites->listInvites()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(config('telemetry.roles'))],
        ]);

        $invite = $this->invites->createInvite(
            $validated['email'],
            $validated['role'],
            $request->user(),
        );

        return response()->json(['data' => $invite], 201);
    }

    public function regenerate(Request $request, int $invite): JsonResponse
    {
        $result = $this->invites->regenerateInvite($invite, $request->user());

        return response()->json(['data' => $result]);
    }

    public function destroy(Request $request, int $invite): JsonResponse
    {
        $this->invites->revokeInvite($invite, $request->user());

        return response()->json(['message' => 'Invite revoked.']);
    }
}
