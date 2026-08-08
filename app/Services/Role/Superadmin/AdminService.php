<?php

namespace App\Services\Role\Superadmin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Role\Superadmin\AdminServiceInterface;
use App\Services\Role\Superadmin\ResourceRegistry;
use App\Services\Role\Superadmin\ResourceValidator;
use App\Services\Role\Superadmin\UserManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        
        // Search across relevant fields
        if ($search = request('search')) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($resource, $searchTerm) {
                // Search logic per resource type
                match($resource) {
                    'classes' => $q->where('class_name', 'like', $searchTerm)
                                   ->orWhere('grade_level', 'like', $searchTerm),
                    'subjects' => $q->where('subject_name', 'like', $searchTerm),
                    'staff-units' => $q->where('unit_name', 'like', $searchTerm),
                    'locations' => $q->where('location_name', 'like', $searchTerm)
                                    ->orWhere('location_type', 'like', $searchTerm),
                    'violation-types' => $q->where('violation_name', 'like', $searchTerm),
                    'damage-categories' => $q->where('category_name', 'like', $searchTerm),
                    default => $q,
                };
            });
        }
        
        // Status filter
        if ($status = request('status')) {
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
        $data['is_active'] = $request->boolean('is_active');
        if ($resource === 'violation-types') {
            $data['created_by'] = $request->user()->id;
        }
        $model::create($data);

        return back()->with('status', 'Data tersimpan.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        [$model] = $this->registry->getModelAndFields($resource);
        $data = $request->validate($this->validator->rulesFor($resource));
        $data['is_active'] = $request->boolean('is_active');
        $model::findOrFail($id)->update($data);

        return back()->with('status', 'Data diperbarui.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        [$model] = $this->registry->getModelAndFields($resource);
        $model::findOrFail($id)->delete();

        return back()->with('status', 'Data dihapus/nonaktif.');
    }

    public function users(): View
    {
        $query = User::query();
        
        // Search by name and email
        if ($search = request('search')) {
            $searchTerm = "%{$search}%";
            $query->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
        }
        
        // Filter by role
        if ($role = request('role')) {
            $query->where('role', $role);
        }
        
        // Filter by status
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
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validator->userRules());
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        $user = new User();
        $this->userManager->fillUserExplicitly($user, $data);

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

        $this->userManager->fillUserExplicitly($user, $data);

        return back()->with('status', 'User diperbarui.');
    }

    public function audit(): View
    {
        $query = AuditLog::query();
        
        // Search by actor or action
        if ($search = request('search')) {
            $searchTerm = "%{$search}%";
            $query->where('actor_type', 'like', $searchTerm)
                  ->orWhere('action', 'like', $searchTerm);
        }
        
        // Filter by action type
        if ($action = request('action')) {
            $query->where('action', $action);
        }
        
        // Filter by date range
        if ($from_date = request('from_date')) {
            $query->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date = request('to_date')) {
            $query->whereDate('created_at', '<=', $to_date);
        }

        return view('admin.audit', [
            'logs' => $query->latest()->paginate(30),
            'actions' => AuditLog::distinct()->pluck('action'),
        ]);
    }
}
