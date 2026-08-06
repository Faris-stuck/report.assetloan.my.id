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

        return view('admin.master.index', [
            'resource' => $resource,
            'fields' => $fields,
            'items' => $model::latest()->paginate(20),
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
        return view('admin.users.index', ['users' => User::latest()->paginate(20)]);
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
        return view('admin.audit', ['logs' => AuditLog::latest()->paginate(30)]);
    }
}
