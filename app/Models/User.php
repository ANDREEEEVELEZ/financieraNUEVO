<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'active',
    ];

    // Mutators para convertir automáticamente a mayúsculas
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper($value);
    }

    public function setEmailAttribute($value)
    {
        // Para el email, mantener minúsculas para compatibilidad
        $this->attributes['email'] = strtolower($value);
    }

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
    return $this->hasAnyRole([
        'super_admin',
        'Jefe de operaciones',
        'Jefe de creditos',
        'Asesor',
    ]);
}

}
