<?php

namespace App\Services\Role\Superadmin;

use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Role\Superadmin\AdminServiceInterface;
use App\Services\Role\Superadmin\ResourceRegistry;
use App\Services\Role\Superadmin\ResourceValidator;
use App\Services\Role\Superadmin\UserManager;
use App\Support\RequestFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminService implements AdminServiceInterface
{
    public function __construct(
        private ResourceRegistry $registry,
        private ResourceValidator $validator,
        private UserManager $userManager
    ) {
    }

    public function master(string $resource): View
    {
        [$model, $fields] = $this->registry->getModelAndFields($resource);

        $classesModel = $this->registry->getClassesModel();
        
        // Build query with search/filter support
        $query = $model::query();

        // Resource ini menampilkan nama kelas di tabel, jadi relasinya dimuat
        // di depan agar tidak menembak satu query per baris.
        if ($resource === 'students') {
            $query->with('class');
        }
        
        // Search across relevant fields
        if ($search = RequestFilters::searchTerm(request('search'))) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($resource, $searchTerm) {
                // Search logic per resource type
                match($resource) {
                    'classes' => $q->where('class_name', 'like', $searchTerm)
                                   ->orWhere('grade_level', 'like', $searchTerm),
                    'subjects' => $q->where('subject_name', 'like', $searchTerm),
                    'staff-units' => $q->where('unit_name', 'like', $searchTerm),
                    'violation-types' => $q->where('violation_name', 'like', $searchTerm),
                    'damage-categories' => $q->where('category_name', 'like', $searchTerm),
                    'students' => $q->where('name', 'like', $searchTerm)
                                    ->orWhere('nis', 'like', $searchTerm),
                    default => $q,
                };
            });
        }

        // Status filter. Tabel students tidak punya kolom is_active, jadi filter
        // ini harus dilewati untuk resource itu agar tidak memicu error kolom
        // tidak dikenal.
        if (($status = request('status')) && $this->resourceHasActiveFlag($resource)) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return view('admin.master.index', [
            'resource' => $resource,
            'fields' => $fields,
            'items' => $query->latest()->paginate(20),
            'classes' => $classesModel::where('is_active', true)->orderBy('class_name')->get(),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        [$model] = $this->registry->getModelAndFields($resource);
        $data = $request->validate($this->validator->rulesFor($resource));
        if ($this->resourceHasActiveFlag($resource)) {
            $data['is_active'] = $request->boolean('is_active');
        }
        if ($resource === 'violation-types') {
            $data['created_by'] = $request->user()->id;
        }
        $record = $model::create($data);
        $this->audited($request, 'MASTER_CREATED', $record);
        $this->forgetReferenceCaches();

        return back()->with('status', 'Data tersimpan.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        [$model] = $this->registry->getModelAndFields($resource);
        $data = $request->validate($this->validator->rulesFor($resource, $id));
        if ($this->resourceHasActiveFlag($resource)) {
            $data['is_active'] = $request->boolean('is_active');
        }
        $record = $model::findOrFail($id);
        $before = $record->getOriginal();
        $record->update($data);
        $this->audited($request, 'MASTER_UPDATED', $record, $before);
        $this->forgetReferenceCaches();

        return back()->with('status', 'Data diperbarui.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        [$model] = $this->registry->getModelAndFields($resource);
        $record = $model::findOrFail($id);
        $before = $record->getOriginal();

        // Master rows are referenced by reports/students via RESTRICT foreign keys.
        // Surface a readable message instead of letting the driver bubble up a 500.
        try {
            $record->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw ValidationException::withMessages([
                    'delete' => 'Data ini masih dipakai oleh data lain sehingga tidak dapat dihapus. Nonaktifkan data ini sebagai gantinya.',
                ]);
            }

            throw $e;
        }

        $this->audited(request(), 'MASTER_DELETED', $record, $before);
        $this->forgetReferenceCaches();

        // Baris master benar-benar dihapus di sini; penonaktifan adalah aksi lain
        // (checkbox "Aktif" pada form edit). Pesan lama menyebut dua-duanya
        // sehingga operator tidak yakin datanya masih ada atau tidak.
        return back()->with('status', 'Data dihapus.');
    }

    public function users(): View
    {
        $query = User::query();

        if ($search = RequestFilters::searchTerm(request('search'))) {
            $searchTerm = "%{$search}%";

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($role = request('role')) {
            $query->where('role', $role);
        }

        if ($status = request('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return view('admin.users.index', [
            'users' => $query->latest()->paginate(20),
            'roles' => User::ROLES,
            'activeSuperadminCount' => CacheHelper::remember(
                'laporin:admin:active_superadmin_count',
                60,
                fn () => User::where('role', 'superadmin')
                    ->where('is_active', true)
                    ->count()
            ),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validator->userRules());
        $data['password'] = Hash::make($data['password']);
        // No default: an unchecked "Aktif" checkbox must create an inactive account.
        $data['is_active'] = $request->boolean('is_active');

        $user = new User();
        $this->userManager->fillUserExplicitly($user, $data);
        $this->audited($request, 'USER_CREATED', $user);
        $this->forgetUserCaches();

        return back()->with('status', 'User dibuat.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->userManager->ensureNotLegacy($user);

        $data = $request->validate($this->validator->userRules($user));
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');

        if ($this->userManager->wouldRemoveLastActiveSuperadmin($user, $data)) {
            throw ValidationException::withMessages([
                'role' => 'Tidak boleh menonaktifkan atau menurunkan role superadmin aktif terakhir.',
            ]);
        }

        $before = $user->getOriginal();
        $this->userManager->fillUserExplicitly($user, $data);
        $this->audited($request, 'USER_UPDATED', $user, $before);
        $this->forgetUserCaches();

        return back()->with('status', 'User diperbarui.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $currentUser = request()->user();

        if ($currentUser && $currentUser->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menghapus akun yang sedang digunakan.',
            ]);
        }

        if (
            $user->role === 'superadmin'
            && $user->is_active
            && ! User::where('role', 'superadmin')
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'user' => 'SuperAdmin aktif terakhir tidak dapat dihapus.',
            ]);
        }

        $before = $user->getOriginal();
        $user->delete();
        $this->audited(request(), 'USER_DELETED', $user, $before);
        $this->forgetUserCaches();

        return back()->with('status', 'Pengguna berhasil dihapus.');
    }

    public function audit(): View
    {
        $query = AuditLog::query();
        
        // Search by user identity, actor type, or action.
        if ($search = RequestFilters::searchTerm(request('search'))) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('actor_type', 'like', $searchTerm)
                    ->orWhere('action', 'like', $searchTerm)
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        // Filter by action type
        if ($action = request('action')) {
            $query->where('action', $action);
        }

        // Filter by date range. Nilai yang bukan tanggal Y-m-d diabaikan supaya
        // audit trail tidak tampil kosong tanpa alasan yang bisa dilihat.
        if ($fromDate = RequestFilters::isoDate(request('from_date'))) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = RequestFilters::isoDate(request('to_date'))) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return view('admin.audit', [
            'logs' => $query->with('user')->latest()->paginate(30),
            'actions' => CacheHelper::remember(
                'laporin:admin:audit_actions',
                300,
                fn () => AuditLog::distinct()->pluck('action')
            ),
        ]);
    }

    /**
     * Only the six master tables carry an is_active flag; students does not.
     * Writing the key unconditionally would blow up on an unknown column.
     */
    private function resourceHasActiveFlag(string $resource): bool
    {
        return $resource !== 'students';
    }

    /**
     * Persist every superadmin mutation so /admin/audit reflects reality.
     * Failures here must never break the mutation the operator just performed.
     */
    private function audited(?Request $request, string $action, Model $record, ?array $old = null): void
    {
        try {
            $actor = $request?->user();
            $ip = $request?->ip();

            AuditLog::create([
                'user_id' => $actor?->id,
                'actor_type' => $actor?->role ?? 'system',
                'action' => $action,
                'model_type' => $record::class,
                'model_id' => $record->getKey(),
                'old_values' => $old === null ? null : $this->redactSensitive($old),
                'new_values' => $this->redactSensitive($record->getAttributes()),
                'ip_address_hash' => is_string($ip) && $ip !== ''
                    ? hash_hmac('sha256', $ip, (string) config('app.key'))
                    : null,
                'user_agent' => $request === null
                    ? null
                    : mb_substr((string) $request->userAgent(), 0, 255),
            ]);

            // The /admin/audit action dropdown is cached for 5 minutes; a brand new
            // action type would otherwise be missing from its own filter.
            CacheHelper::forget('laporin:admin:audit_actions');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Audit rows are readable by every superadmin, so credentials never enter them.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redactSensitive(array $values): array
    {
        foreach (['password', 'remember_token', 'access_code_hash'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        // 23000 covers MySQL/MariaDB 1451 and SQLite FK constraint failures.
        return (string) $e->getCode() === '23000'
            && str_contains(mb_strtolower($e->getMessage()), 'foreign key');
    }

    private function forgetUserCaches(): void
    {
        CacheHelper::forget('laporin:admin:active_superadmin_count');
    }

    /**
     * Wizard laporan publik menyimpan daftar data master di cache selama satu
     * jam. Tanpa invalidasi di sini, kelas atau kategori yang baru ditambah
     * Superadmin tidak muncul di form pelapor sampai TTL habis.
     *
     * Kegagalan cache tidak boleh menggagalkan mutasi yang sudah tersimpan di
     * database, jadi errornya hanya dilaporkan.
     */
    private function forgetReferenceCaches(): void
    {
        try {
            foreach (['classes', 'subjects', 'staff_units', 'damage_categories'] as $name) {
                CacheHelper::forget('laporin:reference:'.$name);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
