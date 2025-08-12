<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function assignRole(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($id);
        $role = Role::findById($request->role_id);
        $user->syncRoles([$role]);

        return redirect()->back()->with('success', 'Rolle erfolgreich zugewiesen.');
    }
}
