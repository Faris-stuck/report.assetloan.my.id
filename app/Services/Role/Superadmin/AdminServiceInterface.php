<?php

namespace App\Services\Role\Superadmin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

interface AdminServiceInterface
{
    public function master(string $resource): View;

    public function store(Request $request, string $resource): RedirectResponse;

    public function update(Request $request, string $resource, int $id): RedirectResponse;

    public function destroy(string $resource, int $id): RedirectResponse;

    public function users(): View;

    public function storeUser(Request $request): RedirectResponse;

    public function updateUser(Request $request, User $user): RedirectResponse;

    public function audit(): View;
}
