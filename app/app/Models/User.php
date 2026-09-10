<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAnalyst(): bool
    {
        return in_array($this->role, ['admin', 'analyst'], true);
    }

    public function canManage(): bool
    {
        return $this->is_active && $this->isAdmin();
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function canReadAnalytics(): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'analyst', 'viewer'], true);
    }

    public function canReadEvents(): bool
    {
        return $this->canReadAnalytics();
    }

    public function canExport(): bool
    {
        return $this->is_active && $this->isAnalyst();
    }

    public function canManageDashboards(): bool
    {
        return $this->is_active && $this->isAnalyst();
    }
}
