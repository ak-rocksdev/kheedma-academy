<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberPasswordController extends Controller
{
    public function requestForm(): View
    {
        return view('member.forgot-password');
    }

    /**
     * Always answer with the same message so account emails cannot be
     * enumerated from this endpoint.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Jika email terdaftar, tautan atur ulang sudah kami kirim. Cek kotak masukmu.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('member.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            $data,
            function ($user, string $password): void {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ]);
        }

        return redirect()->route('member.login')->with('reset', 'Kata sandi berhasil diubah. Silakan masuk.');
    }
}
