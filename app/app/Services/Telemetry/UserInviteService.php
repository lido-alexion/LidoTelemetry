<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryUserInvite;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserInviteService
{
    public const EXPIRY_HOURS = 72;

    public function __construct(
        protected AuditService $audit,
    ) {}

    public function purgeExpired(): int
    {
        return TelemetryUserInvite::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInvites(): array
    {
        return TelemetryUserInvite::query()
            ->with('invitedBy:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TelemetryUserInvite $invite) => $this->toAdminPayload($invite))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createInvite(string $email, string $role, User $admin): array
    {
        $result = $this->create($admin, $email, $role);

        return $this->toAdminPayload($result['invite'], $result['raw_token']);
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateInvite(int $inviteId, User $admin): array
    {
        $invite = TelemetryUserInvite::query()->findOrFail($inviteId);
        $result = $this->regenerate($invite, $admin->id);

        return $this->toAdminPayload($result['invite'], $result['raw_token']);
    }

    public function revokeInvite(int $inviteId, User $admin): void
    {
        $invite = TelemetryUserInvite::query()->findOrFail($inviteId);
        $this->revoke($invite, $admin->id);
    }

    public function pendingForEmail(string $email): ?TelemetryUserInvite
    {
        $this->purgeExpired();

        return TelemetryUserInvite::query()
            ->where('email', $this->normalizeEmail($email))
            ->whereNull('accepted_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();
    }

    public function findByToken(string $rawToken): ?TelemetryUserInvite
    {
        $this->purgeExpired();

        $invite = TelemetryUserInvite::query()
            ->where('token', $this->hashToken($rawToken))
            ->first();

        if ($invite === null) {
            return null;
        }

        if ($invite->isAccepted()) {
            return $invite;
        }

        if ($invite->isExpired()) {
            $invite->delete();

            return null;
        }

        return $invite;
    }

    /**
     * @return array{invite: TelemetryUserInvite, raw_token: string}
     */
    public function create(User $admin, string $email, string $role = 'analyst'): array
    {
        $email = $this->normalizeEmail($email);

        TelemetryUserInvite::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '<', now())
            ->delete();

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        if ($this->pendingForEmail($email) !== null) {
            throw ValidationException::withMessages([
                'email' => ['A pending invite already exists for this email. Regenerate or revoke it first.'],
            ]);
        }

        $rawToken = $this->generateRawToken();

        $invite = TelemetryUserInvite::query()->create([
            'email' => $email,
            'token' => $this->hashToken($rawToken),
            'role' => $role,
            'invited_by_user_id' => $admin->id,
            'expires_at' => now()->addHours(self::EXPIRY_HOURS),
        ]);

        $this->audit->log('user_invite.created', $admin->id, 'user_invite', (string) $invite->id, [
            'email' => $email,
            'role' => $role,
        ]);

        return [
            'invite' => $invite,
            'raw_token' => $rawToken,
        ];
    }

    /**
     * @return array{invite: TelemetryUserInvite, raw_token: string}
     */
    public function regenerate(TelemetryUserInvite $invite, ?int $actorUserId = null): array
    {
        if ($invite->isAccepted()) {
            throw ValidationException::withMessages([
                'invite' => ['This invite was already accepted.'],
            ]);
        }

        $rawToken = $this->generateRawToken();
        $invite->token = $this->hashToken($rawToken);
        $invite->save();

        $this->audit->log('user_invite.regenerated', $actorUserId, 'user_invite', (string) $invite->id);

        return [
            'invite' => $invite->fresh(),
            'raw_token' => $rawToken,
        ];
    }

    public function revoke(TelemetryUserInvite $invite, ?int $actorUserId = null): void
    {
        if ($invite->isAccepted()) {
            throw ValidationException::withMessages([
                'invite' => ['Accepted invites cannot be revoked.'],
            ]);
        }

        $inviteId = (string) $invite->id;
        $invite->delete();

        $this->audit->log('user_invite.revoked', $actorUserId, 'user_invite', $inviteId);
    }

    /**
     * @return array{user: User, invite: TelemetryUserInvite}
     */
    public function accept(string $rawToken, string $name, string $password): array
    {
        $invite = $this->findByToken($rawToken);

        if ($invite === null) {
            throw ValidationException::withMessages([
                'token' => ['This invite link is invalid or has expired. Please contact your administrator for a new invite.'],
            ]);
        }

        if ($invite->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => ['This invite was already used. You can sign in with your password.'],
            ]);
        }

        if (User::query()->where('email', $invite->email)->exists()) {
            $invite->delete();
            throw ValidationException::withMessages([
                'token' => ['An account already exists for this email. Try signing in.'],
            ]);
        }

        $displayName = trim($name) !== '' ? trim($name) : Str::before($invite->email, '@');

        $user = User::query()->create([
            'name' => $displayName,
            'email' => $invite->email,
            'password' => Hash::make($password),
            'role' => $invite->role,
            'is_active' => true,
        ]);

        $invite->accepted_at = now();
        $invite->user_id = $user->id;
        $invite->save();

        $this->audit->log('user_invite.accepted', $user->id, 'user_invite', (string) $invite->id);

        return [
            'user' => $user,
            'invite' => $invite->fresh(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminPayload(TelemetryUserInvite $invite, ?string $rawToken = null): array
    {
        $status = $invite->isAccepted()
            ? 'accepted'
            : ($invite->isExpired() ? 'expired' : 'pending');

        $includeUrl = $status === 'pending' && $rawToken !== null && $rawToken !== '';

        return [
            'id' => $invite->id,
            'email' => $invite->email,
            'role' => $invite->role,
            'status' => $status,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'accepted_at' => $invite->accepted_at?->toIso8601String(),
            'created_at' => $invite->created_at?->toIso8601String(),
            'invited_by' => $invite->invitedBy?->only(['id', 'name', 'email']),
            'invite_url' => $includeUrl ? $this->inviteUrl($rawToken) : null,
            'url_available' => $includeUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(TelemetryUserInvite $invite): array
    {
        return [
            'email' => $invite->email,
            'role' => $invite->role,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'status' => $invite->isAccepted() ? 'accepted' : 'pending',
        ];
    }

    public function inviteUrl(string $rawToken): string
    {
        return rtrim((string) config('app.url'), '/').'/invite/'.$rawToken;
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    protected function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    protected function generateRawToken(): string
    {
        do {
            $token = Str::random(64);
            $hash = $this->hashToken($token);
        } while (TelemetryUserInvite::query()->where('token', $hash)->exists());

        return $token;
    }
}
