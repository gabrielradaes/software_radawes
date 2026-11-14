<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'branch'])->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        User::create($data);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === $request->user()->id) {
            $data['is_active'] = true;   // nadie se desactiva a sí mismo
        }
        $user->update($data);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'No puedes eliminar tu propio usuario.']);
        }
        if ($user->posts()->exists() || $user->sales()->exists()) {
            $user->update(['is_active' => false]);

            return back()->with('status', 'El usuario tiene historial: se desactivó en lugar de eliminarse.');
        }
        $user->delete();

        return back()->with('status', 'Usuario eliminado.');
    }

    protected function formData(User $user): array
    {
        return ['user' => $user, 'roles' => Role::all(), 'branches' => Branch::orderBy('name')->get()];
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'role_id' => 'rol', 'password' => 'contraseña']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
