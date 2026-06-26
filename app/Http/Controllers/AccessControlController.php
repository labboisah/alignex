<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccessControlsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('AccessControls/Index', [
            'roles' => Role::query()
                ->with('permissions:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role) => [
                    'name' => $role->name,
                    'label' => $role->label,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'permissions' => $role->permissions->pluck('name')->values(),
                ]),
            'permissions' => Permission::query()
                ->orderBy('group')
                ->orderBy('label')
                ->get(['name', 'label', 'group', 'description']),
        ]);
    }

    public function update(UpdateAccessControlsRequest $request, AccessControlService $accessControlService): RedirectResponse
    {
        $accessControlService->syncRolePermissions($request->validated('roles'));

        return back()->with('success', 'Access controls updated.');
    }
}
