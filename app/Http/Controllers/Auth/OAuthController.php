<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\OAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class OAuthController extends Controller
{
    public function __construct(private readonly OAuthService $oauth) {}

    /** Redirect pengguna ke halaman otorisasi OAuth RS. */
    public function redirect(Request $request): RedirectResponse
    {
        if (!$this->oauth->isEnabled()) {
            return redirect()->route('login')->withErrors(['oauth' => 'OAuth RS belum dikonfigurasi.']);
        }

        ['url' => $url, 'state' => $state] = $this->oauth->buildAuthorizationUrl();

        $request->session()->put('oauth_state', $state);

        return redirect()->away($url);
    }

    /** Terima callback dari OAuth RS, login pengguna via session. */
    public function callback(Request $request): RedirectResponse
    {
        // Validasi state (proteksi CSRF)
        $storedState = $request->session()->pull('oauth_state');
        if (!$storedState || !hash_equals($storedState, $request->query('state', ''))) {
            return redirect()->route('login')->withErrors(['oauth' => 'State tidak valid. Silakan coba lagi.']);
        }

        // Tangani error dari server OAuth (misal: user menolak izin)
        if ($request->has('error')) {
            $desc = $request->query('error_description', $request->query('error'));
            return redirect()->route('login')->withErrors(['oauth' => $desc]);
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect()->route('login')->withErrors(['oauth' => 'Authorization code tidak ditemukan.']);
        }

        try {
            $token    = $this->oauth->exchangeCodeForToken($code);
            $userInfo = $this->oauth->getUserInfo($token);
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors(['oauth' => $e->getMessage()]);
        }

        $email    = $userInfo['email']    ?? null;
        $username = $userInfo['preferred_username'] ?? null;

        if (!$email && !$username) {
            return redirect()->route('login')->withErrors(['oauth' => 'Data pengguna tidak lengkap dari server OAuth.']);
        }

        // Cari user berdasarkan email, fallback ke username
        $user = ($email ? User::where('email', $email)->first() : null)
            ?? ($username ? User::where('username', $username)->first() : null);

        if (!$user) {
            // Buat akun lokal baru — tanpa password (login hanya via OAuth)
            $user = User::create([
                'username'  => $username ?? Str::before($email, '@'),
                'name'      => $userInfo['name'] ?? $username ?? Str::before($email, '@'),
                'email'     => $email ?? ($username . '@oauth.local'),
                'password'  => null,
                'role'      => $userInfo['role'] ?? 'user',
                'is_active' => true,
            ]);
        } else {
            // Sinkronisasi data dari OAuth RS
            $user->update(array_filter([
                'name' => $userInfo['name']   ?? $user->name,
                'role' => $userInfo['role']   ?? $user->role,
            ]));
        }

        if (!$user->is_active) {
            return redirect()->route('login')->withErrors(['oauth' => 'Akun Anda tidak aktif. Hubungi administrator.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        ActivityLog::log(
            type: 'user_login',
            subject: 'Login via OAuth RS',
            description: null,
            properties: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'provider'   => 'oauth_rs',
            ],
        );

        return redirect()->intended(route('home'));
    }
}
