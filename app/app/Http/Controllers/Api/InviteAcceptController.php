<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\UserInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InviteAcceptController extends Controller
{
    public function __construct(
        protected UserInviteService $invites,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invite = $this->invites->findByToken($token);

        if ($invite === null) {
            return response()->json([
                'valid' => false,
                'message' => 'This invite link is invalid or has expired. Please contact your administrator for a new invite.',
            ], 410);
        }

        if ($invite->accepted_at !== null) {
            return response()->json([
                'valid' => false,
                'status' => 'accepted',
                'message' => 'This invite was already used. You can sign in with your password.',
            ], 409);
        }

        return response()->json([
            'valid' => true,
            'data' => $this->invites->toPublicPayload($invite),
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $result = $this->invites->accept(
            $validated['token'],
            $validated['name'] ?? '',
            $validated['password'],
        );

        $user = $result['user'];
        $remember = $request->boolean('remember');

        Auth::guard('web')->login($user, $remember);
        Auth::shouldUse('web');
        $request->session()->put('logged_in_at', now()->timestamp);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Account created. You are now signed in.',
            'user' => $user,
        ], 201);
    }
}
