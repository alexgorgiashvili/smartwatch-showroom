<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $data['password'] = bcrypt($data['password']);
        $data['is_admin'] = $request->boolean('is_admin');

        User::create($data);

        return redirect()->route('admin.users.index')
            ->with('status', 'User created successfully.');
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()->route('admin.users.index')
                ->with('status', 'You cannot change your own admin status.');
        }

        $user->update([
            'is_admin' => ! $user->is_admin,
        ]);

        return redirect()->route('admin.users.index')
            ->with('status', 'User role updated.');
    }
}
