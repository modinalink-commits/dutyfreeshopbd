<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function show()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = ['email' => strtolower($data['email']), 'password' => $data['password'], 'is_active' => true];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::create(['action' => 'login_failed', 'details' => strtolower($data['email']), 'ip' => $request->ip()]);
            return back()->withErrors(['email' => 'Email or password is not correct.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('login');

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function editPassword()
    {
        return view('password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $request->user();
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Choose a password different from the old one.']);
        }

        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        AuditLog::record('password_changed');

        return redirect('/')->with('ok', 'Password changed.');
    }
}
