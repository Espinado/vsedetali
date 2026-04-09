<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Staff extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $table = 'staff';

    protected string $guard_name = 'staff';

    protected static function newFactory(): \Database\Factories\StaffFactory
    {
        return \Database\Factories\StaffFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'invite_token_hash',
        'invite_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'accountant', 'warehouse']);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function hasPasswordSet(): bool
    {
        $p = $this->attributes['password'] ?? null;

        return $p !== null && $p !== '';
    }
}
