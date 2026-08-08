<?php

namespace App\Http\Controllers\Role\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Role\Superadmin\AdminServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private AdminServiceInterface $service)
    {
    }

    public function master(string $resource): View
    {
        return $this->service->master($resource);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        return $this->service->store($request, $resource);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        return $this->service->update($request, $resource, $id);
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        return $this->service->destroy($resource, $id);
    }

    public function users(): View
    {
        return $this->service->users();
    }

    public function storeUser(Request $request): RedirectResponse
    {
        return $this->service->storeUser($request);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        return $this->service->updateUser($request, $user);
    }

    public function destroyUser(User $user): RedirectResponse
    {
        return $this->service->destroy('users', $user->id);
    }

    public function audit(): View
    {
        return $this->service->audit();
    }
}
