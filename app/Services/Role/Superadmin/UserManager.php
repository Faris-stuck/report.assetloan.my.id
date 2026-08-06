<?php

namespace App\Services\Role\Superadmin;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function fillUserExplicitly(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (array_key_exists('password', $data)) {
            $user->password = $data['password'];
        }
        $user->role = $data['role'];
        $user->phone = $data['phone'] ?? null;
        $user->is_active = (bool) ($data['is_active'] ?? false);
        $user->save();
    }

    public function wouldRemoveLastActiveSuperadmin(User $user, array $data): bool
    {
        if ($user->role !== 'superadmin' || ! $user->is_active) {
            return false;
        }

        $willRemainActiveSuperadmin = ($data['role'] ?? $user->role) === 'superadmin'
            && (bool) ($data['is_active'] ?? $user->is_active);

        if ($willRemainActiveSuperadmin) {
            return false;
        }

        return ! User::where('role', 'superadmin')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();
    }

    public function ensureNotLegacy(User $user): void
    {
        if ($user->role_deactivated_at !== null || in_array($user->role, User::LEGACY_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Akun legacy Guru/Siswa telah diarsipkan dan tidak dapat diaktifkan atau diubah menjadi role operasional.',
            ]);
        }
    }
}
