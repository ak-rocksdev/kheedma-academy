<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberAuthController extends Controller
{
    /** Member login page (public site — the admin SPA has its own). */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->home(Auth::user());
        }

        // Funnel pages may ask to come back after login (?redirect=/program/x/daftar).
        // Local paths only, so this can never become an open redirect.
        $target = $request->query('redirect');
        if (is_string($target) && str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_starts_with($target, '/\\')) {
            $request->session()->put('url.intended', $target);
        }

        return view('member.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun ini dinonaktifkan. Hubungi admin.',
            ]);
        }

        $request->session()->regenerate();

        return $this->home($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Staff belong in the admin panel; members in the member area. */
    private function home($user): RedirectResponse
    {
        if ($user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        return redirect()->intended(route('member.area'));
    }
}
