<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MemberAreaController extends Controller
{
    /** Minimal member home: identity + membership; products/announcements land later. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Mid-session deactivation must end the session here too (the admin
        // API enforces this via EnsureUserIsActive; the member area is the
        // web counterpart).
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('member.login');
        }

        if ($user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $person = $user->person()->with(['communityMembership', 'applications' => fn ($q) => $q->latest(), 'applications.program:id,name'])->first();

        return view('member.akun', [
            'user' => $user,
            'person' => $person,
            'membership' => $person?->communityMembership,
            'applications' => $person?->applications ?? collect(),
        ]);
    }
}
