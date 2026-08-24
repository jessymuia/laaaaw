<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_USERS)) {
            abort(403);
        }

        $query = User::with('roles')->orderBy('id', 'desc');
        $usersWithRoles = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['name', 'email', 'phone_number', 'department', 'hire_date'],
            ['name', 'email', 'phone_number', 'department']
        );

        return $this->response(true, 'success', $usersWithRoles, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(User $user): array
    {
        $roles = $user->roles;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'department' => $user->department,
            'hire_date' => Carbon::parse($user->hire_date)->format('d/m/Y'),
            'role' => implode(',', $roles->pluck('name')->toArray()),
            'role_id' => $roles->pluck('id')->toArray(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_USERS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date_format:d/m/Y',
            'role' => 'nullable',
        ]);

        // Str::password() only exists from Laravel 10 — on this Laravel 9
        // install it throws BadMethodCallException (a 500 on every user
        // creation). Str::random() is the 9.x-compatible equivalent.
        $temporaryPassword = Str::random(16);

        $user = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'department' => $request->department,
            'hire_date' => Carbon::createFromFormat('d/m/Y', $request->hire_date)->format('Y-m-d'),
            'password' => Hash::make($temporaryPassword),
        ]);

        // TODO: email $temporaryPassword to the user via a proper invite/reset
        // flow instead of a fixed known string, once ENG-3's mail queue is in place.

        if ($request->has('role')) {
            $roles = $request->role; // Assuming 'role' is an array
            if (is_array($roles)) {
                // Assign multiple roles at once
                $user->assignRole($roles);
            } else {
                // Handle single role as fallback
                $role = Role::find($roles);
                if ($role) {
                    $user->assignRole($role);
                }
            }
        }

        return $this->response(true, 'success', $this->formatRow($user->load('roles')), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_USERS)) {
            abort(403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,'.$id,
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date_format:d/m/Y',
            'role' => 'nullable',
        ]);

        $user->name = $request->name;
        $user->phone_number = $request->phone_number;
        $user->department = $request->department;
        $user->hire_date = Carbon::createFromFormat('d/m/Y', $request->hire_date)->format('Y-m-d');
        $user->email = $request->email;
        $user->save();

        if ($request->has('role')) {
            $roles = $request->role; // Assuming 'role' can be an array or a single value
            if (is_array($roles)) {
                // Sync roles directly with an array
                $user->syncRoles($roles);
            } else {
                // Handle single role as fallback
                $role = Role::find($roles);
                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }
        }

        return $this->response(true, 'success', $this->formatRow($user->fresh('roles')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * User accounts are soft-deleted, never hard-deleted — too many other
     * tables reference users by id (created_by, assigned_to, recorded_by,
     * etc.) for a hard delete to be safe. A deactivated user simply stops
     * being able to log in (soft-deleted users fail Auth::attempt's
     * default query scope) while every historical record they created
     * remains intact and attributable.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_USERS)) {
            abort(403);
        }

        $user = User::findOrFail($id);

        if ($user->id === auth()->user()->id) {
            return $this->response(false, 'You cannot deactivate your own account', null, 422);
        }

        // Same reason as booted()/update() above: `users` has no
        // deleted_by column, unlike every other soft-deletable table.
        $user->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    public function getRolesDropdown(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_ROLES)) {
            abort(403);
        }

        $roles = Role::orderByDesc('id')->get();
        $roles = $roles->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
            ];
        });

        return $this->response(true, 'success', $roles, 200);
    }
}
