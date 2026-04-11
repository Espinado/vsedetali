<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class SellerStaff extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $table = 'seller_staff';

    protected string $guard_name = 'seller_staff';

    protected $fillable = [
        'seller_id',
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
            'invite_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'seller') {
            return false;
        }

        $seller = $this->seller;
        if ($seller === null || $seller->isBlocked()) {
            return false;
        }

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
