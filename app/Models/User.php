<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLES = ['superadmin', 'kesiswaan', 'sarpras', 'wali_kelas'];

    public const LEGACY_ROLES = ['guru', 'siswa'];

    // Keep privilege-bearing fields out of mass assignment. AdminController assigns them explicitly.
    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'legacy_is_active' => 'boolean',
            'role_deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public static function normalizeRole(?string $role): string
    {
        return str_replace(['_', '-', ' '], '', strtolower((string) $role));
    }

    public function normalizedRole(): string
    {
        return self::normalizeRole($this->role);
    }

    public function isRole(string|array $roles): bool
    {
        $allowed = array_map(fn (string $role): string => self::normalizeRole($role), (array) $roles);

        return in_array($this->normalizedRole(), $allowed, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->isRole('superadmin');
    }

    public function canAccessMenuFor(string|array $roles): bool
    {
        return $this->isSuperadmin() || $this->isRole($roles);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(HomeroomClass::class, 'homeroom_user_id');
    }
}
