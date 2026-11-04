<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users', ['users' => User::query()->orderBy('name')->get(), 'roles' => User::ROLES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        $temp = $this->tempPassword();
        $user = User::create($data + [
            'email' => strtolower($data['email']),
            'password' => $temp,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        AuditLog::record('user_created', $user->email.' ('.$user->role.')');

        return back()->with('temp', ['email' => $user->email, 'password' => $temp]);
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot switch off your own account.']);
        }
        $user->forceFill(['is_active' => ! $user->is_active])->save();
        AuditLog::record($user->is_active ? 'user_activated' : 'user_deactivated', $user->email);

        return back()->with('ok', $user->email.($user->is_active ? ' activated.' : ' deactivated.'));
    }

    public function reset(Request $request, User $user)
    {
        $temp = $this->tempPassword();
        $user->forceFill(['password' => $temp, 'must_change_password' => true])->save();
        AuditLog::record('user_password_reset', $user->email);

        return back()->with('temp', ['email' => $user->email, 'password' => $temp]);
    }

    public function activity()
    {
        return view('activity', ['logs' => AuditLog::query()->with('user')->latest('created_at')->paginate(50)]);
    }

    private function tempPassword(): string
    {
        return Str::password(12, true, true, false);
    }
}
