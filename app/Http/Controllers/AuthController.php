<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (Auth::user()->google2fa_enabled) {
                $request->session()->put([
                    'mfa.user_id' => Auth::id(),
                    'mfa.remember' => $request->boolean('remember'),
                    'mfa.created_at' => now()->timestamp,
                ]);
                Auth::logout();

                return redirect()->route('mfa.challenge');
            }

            AuditLog::record('login', 'Authentication');

            return redirect()->intended(route('dashboard'));
        }

        AuditLog::record('failed_login', 'Authentication', null, [
            'email' => $credentials['email'],
        ]);

        return back()->withErrors(['email' => 'Email atau password tidak sesuai.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', 'Authentication');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        AuditLog::record('change_password', 'Authentication');

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
