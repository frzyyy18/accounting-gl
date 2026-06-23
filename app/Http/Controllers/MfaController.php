<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class MfaController extends Controller
{
    public function challenge()
    {
        abort_unless(session()->has('mfa.user_id'), 404);
        if ($this->mfaChallengeExpired()) {
            session()->forget(['mfa.user_id', 'mfa.remember', 'mfa.created_at']);

            return redirect()->route('login')->withErrors(['email' => 'Sesi MFA kedaluwarsa. Silakan login ulang.']);
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        if ($this->mfaChallengeExpired()) {
            $request->session()->forget(['mfa.user_id', 'mfa.remember', 'mfa.created_at']);

            return redirect()->route('login')->withErrors(['email' => 'Sesi MFA kedaluwarsa. Silakan login ulang.']);
        }

        $user = User::findOrFail(session('mfa.user_id'));

        if (! $this->google2fa()->verifyKey($user->google2fa_secret, $request->input('code'))) {
            AuditLog::record('failed_mfa', 'Authentication', null, ['email' => $user->email], $user->company_id);

            throw ValidationException::withMessages(['code' => 'Kode MFA tidak valid.']);
        }

        Auth::login($user, (bool) session('mfa.remember'));
        $request->session()->forget(['mfa.user_id', 'mfa.remember', 'mfa.created_at']);
        $request->session()->regenerate();
        AuditLog::record('login', 'Authentication');

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request)
    {
        $user = $request->user();

        if (! $user->google2fa_secret) {
            $user->update(['google2fa_secret' => $this->google2fa()->generateSecretKey()]);
        }

        return view('auth.mfa-setup', [
            'enabled' => $user->google2fa_enabled,
            'secret' => $user->google2fa_secret,
            'qrCode' => $this->qrCodeInline($user),
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! $user->google2fa_secret || ! $this->google2fa()->verifyKey($user->google2fa_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Kode MFA tidak valid.']);
        }

        $user->update(['google2fa_enabled' => true]);
        AuditLog::record('enable_mfa', 'Authentication');

        return back()->with('success', 'MFA berhasil diaktifkan.');
    }

    public function disable(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! $user->google2fa_secret || ! $this->google2fa()->verifyKey($user->google2fa_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Kode MFA tidak valid.']);
        }

        $user->update(['google2fa_enabled' => false, 'google2fa_secret' => null]);
        AuditLog::record('disable_mfa', 'Authentication');

        return back()->with('success', 'MFA berhasil dinonaktifkan.');
    }

    private function google2fa()
    {
        return app('pragmarx.google2fa');
    }

    private function mfaChallengeExpired(): bool
    {
        $createdAt = session('mfa.created_at');

        return ! $createdAt || now()->timestamp - (int) $createdAt > 300;
    }

    private function qrCodeInline(User $user): ?string
    {
        try {
            return $this->google2fa()->getQRCodeInline(config('app.name'), $user->email, $user->google2fa_secret);
        } catch (Throwable $exception) {
            Log::warning('Unable to render MFA QR code.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
