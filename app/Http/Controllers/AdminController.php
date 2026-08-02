<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    private array $map = [
        'classes' => [SchoolClass::class, ['class_name', 'grade_level', 'major', 'academic_year', 'room_name', 'is_active']],
        'subjects' => [Subject::class, ['subject_name', 'is_active']],
        'staff-units' => [StaffUnit::class, ['unit_name', 'is_active']],
        'locations' => [Location::class, ['location_name', 'location_type', 'class_id', 'is_active']],
        'violation-types' => [ViolationType::class, ['violation_name', 'point_reduction', 'description', 'is_active']],
        'damage-categories' => [DamageCategory::class, ['category_name', 'is_active']],
    ];

    public function master(string $resource): View
    {
        [$model, $fields] = $this->map[$resource] ?? abort(404);

        return view('admin.master.index', [
            'resource' => $resource,
            'fields' => $fields,
            'items' => $model::latest()->paginate(20),
            'classes' => SchoolClass::where('is_active', true)->orderBy('class_name')->get(),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        [$model] = $this->map[$resource] ?? abort(404);
        $data = $request->validate($this->rulesFor($resource));
        $data['is_active'] = $request->boolean('is_active');
        if ($resource === 'violation-types') {
            $data['created_by'] = $request->user()->id;
        }
        $model::create($data);

        return back()->with('status', 'Data tersimpan.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        [$model] = $this->map[$resource] ?? abort(404);
        $data = $request->validate($this->rulesFor($resource));
        $data['is_active'] = $request->boolean('is_active');
        $model::findOrFail($id)->update($data);

        return back()->with('status', 'Data diperbarui.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        [$model] = $this->map[$resource] ?? abort(404);
        $model::findOrFail($id)->delete();

        return back()->with('status', 'Data dihapus/nonaktif.');
    }

    public function users(): View
    {
        return view('admin.users.index', ['users' => User::latest()->paginate(20)]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate($this->userRules());
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        $user = new User;
        $this->fillUserExplicitly($user, $data);

        return back()->with('status', 'User dibuat.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        if ($user->role_deactivated_at !== null || in_array($user->role, User::LEGACY_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Akun legacy Guru/Siswa telah diarsipkan dan tidak dapat diaktifkan atau diubah menjadi role operasional.',
            ]);
        }

        $data = $request->validate($this->userRules($user));
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');

        if ($this->wouldRemoveLastActiveSuperadmin($user, $data)) {
            throw ValidationException::withMessages([
                'role' => 'Tidak boleh menonaktifkan atau menurunkan role superadmin aktif terakhir.',
            ]);
        }

        $this->fillUserExplicitly($user, $data);

        return back()->with('status', 'User diperbarui.');
    }

    public function audit(): View
    {
        return view('admin.audit', ['logs' => AuditLog::latest()->paginate(30)]);
    }

    private function rulesFor(string $resource): array
    {
        return match ($resource) {
            'classes' => [
                'class_name' => ['required', 'string', 'max:80'],
                'grade_level' => ['required', 'string', 'max:20'],
                'major' => ['nullable', 'string', 'max:80'],
                'academic_year' => ['required', 'string', 'max:20'],
                'room_name' => ['nullable', 'string', 'max:80'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'subjects' => [
                'subject_name' => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'staff-units' => [
                'unit_name' => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'locations' => [
                'location_name' => ['required', 'string', 'max:150'],
                'location_type' => ['nullable', 'string', 'max:80'],
                'class_id' => ['nullable', Rule::exists('classes', 'id')->where('is_active', true)],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'violation-types' => [
                'violation_name' => ['required', 'string', 'max:150'],
                'point_reduction' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'damage-categories' => [
                'category_name' => ['required', 'string', 'max:120'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => abort(404),
        };
    }

    private function userRules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(User::ROLES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function fillUserExplicitly(User $user, array $data): void
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

    private function wouldRemoveLastActiveSuperadmin(User $user, array $data): bool
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
}
