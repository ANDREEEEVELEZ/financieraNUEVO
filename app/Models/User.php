<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->hasOne(User::class, 'persona_id');
    }

    public function asesor()
    {
        return $this->hasOne(Asesor::class);
    }

    /**
     * Determina si el usuario puede acceder a Filament Panel.
     */
public function canAccessPanel(\Filament\Panel $panel): bool
{
    // Debug temporal
    Log::info('canAccessPanel called for user: ' . $this->email);
    Log::info('User active: ' . ($this->active ? 'true' : 'false'));
    Log::info('User roles: ' . implode(', ', $this->getRoleNames()->toArray()));
    
    // Simplificamos temporalmente para debug
    // Si es super admin, siempre permitir acceso
    if ($this->hasRole('super_admin')) {
        Log::info('User has super_admin role - allowing access');
        return true;
    }
    
    $canAccess = $this->active && $this->hasAnyRole([
        'super_admin',
        'Jefe de operaciones',
        'Jefe de creditos',
        'Asesor',
    ]);
    
    Log::info('Can access panel: ' . ($canAccess ? 'true' : 'false'));
    
    return $canAccess;
}

}
