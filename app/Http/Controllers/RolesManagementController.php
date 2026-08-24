<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesManagementController extends Controller
{
    private function unauthorizedError(): JsonResponse
    {
        return $this->response(false, 'Not permitted', null, 403);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_ROLES)) {
            abort(403);
        }

        $query = Role::orderByDesc('id');
        $roles = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['name', 'created_at', 'updated_at'],
            ['name']
        );

        return $this->response(true, 'Roles retrieved successfully', $roles, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     *
     * Also fixes a latent bug: this previously read Auth::user()->timezone,
     * a column that does not exist anywhere on the users table (no
     * per-user timezone preference was ever actually built). Eloquent
     * returns null for an undefined attribute, and passing null into
     * Carbon::setTimezone() would fail — meaning this endpoint could
     * throw on every request. Uses the app's configured timezone instead,
     * which is what every other timestamp in the app already implicitly
     * assumes.
     */
    public function formatRow(Role $role): array
    {
        $timezone = config('app.timezone');
        $role_created_at = Carbon::parse($role->created_at)->setTimezone($timezone);
        $role_updated_at = Carbon::parse($role->updated_at)->setTimezone($timezone);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'created_at' => $role_created_at->format('Y-m-d H:i:s'),
            'updated_at' => $role_updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_ROLES)) {
            return $this->unauthorizedError();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (! empty($data['permissions'])) {
            $permissions = Permission::whereIn('name', $data['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return $this->response(true, 'success', $this->formatRow($role->fresh()), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_ROLES)) {
            return $this->unauthorizedError();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::findOrFail($id);
        $role->name = $data['name'];
        $role->save();
        $role->syncPermissions($data['permissions'] ?? []);

        return $this->response(true, 'success', $this->formatRow($role->fresh()), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $name)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_ROLES)) {
            return $this->unauthorizedError();
        }

        $role = Role::where('name', $name)->firstOrFail();

        if (in_array(strtolower($role->name), ['admin', 'super-admin'], true)) {
            return $this->response(false, 'The admin role cannot be deleted', null, 422);
        }

        $roleId = $role->id;

        if ($role->delete()) {
            return $this->response(true, 'success', ['id' => $roleId], 200);
        }

        return $this->response(false, 'Role could not be deleted', null, 500);
    }

    public function allPermissions()
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_PERMISSIONS)) {
            abort(403);
        }

        $permissions = Permission::all();
        $permissions = $permissions->map(function ($permission) {
            return [
                'name' => $permission->name,
            ];
        });

        return $this->response(true, 'Permissions retrieved successfully', $permissions, 200);
    }

    public function getRolePermissions(string $id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_ROLES)) {
            abort(403);
        }

        $role = Role::where('id', $id)->firstOrFail();
        $permissions = $role->permissions->pluck('name');

        return $this->response(true, 'Permissions retrieved successfully', $permissions, 200);
    }
}
