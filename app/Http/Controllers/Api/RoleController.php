<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('permissions')->get();
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json($role);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api',
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($role, 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:150|unique:roles,name,'.$role->id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['super-admin', 'coordinador'])) {
            return response()->json(['message' => 'No puedes eliminar roles del sistema'], 403);
        }

        $role->delete();

        return response()->json(null, 204);
    }
}
