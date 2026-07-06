<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Roles allowed to access the admin panel. */
    private const STAFF_ROLES = ['admin', 'mentor'];

    /** Session (cookie) login for the admin SPA. */
    public function login(Request $request): JsonResponse
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

        // Only staff may use the admin panel; participants are turned away.
        if (! $user->hasAnyRole(self::STAFF_ROLES)) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses ke panel admin.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->profile($user)]);
    }

    /** The authenticated admin user (used to hydrate the SPA on load). */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function profile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
