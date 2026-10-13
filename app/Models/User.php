<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'permissions',
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
            'permissions' => 'array',
        ];
    }

    /**
     * Check if user has permission to access a module
     * 
     * @param string $module (e.g. 'rfu-unit', 'monitoring-absensi', etc.)
     * @param string $level ('read' or 'write')
     */
    public function canAccessModule(string $module, string $level = 'read'): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $permissions = $this->permissions ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $userLevel = $permissions[$module] ?? null;

        // If explicitly defined in permissions JSON
        if ($userLevel) {
            if ($level === 'read') {
                return in_array($userLevel, ['read', 'write']);
            }
            if ($level === 'write') {
                return $userLevel === 'write';
            }
            return false;
        }

        // Fallback for legacy roles when permissions map is not configured
        if ($this->role === 'hrd') {
            return in_array($module, ['rfu-unit', 'monitoring-absensi', 'sp-karyawan', 'manpower']);
        }

        if ($this->role === 'viewer') {
            return in_array($module, ['manpower', 'sp-karyawan']) && $level === 'read';
        }

        return false;
    }
}
