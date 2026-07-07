<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectNonStaffFromAdmin
{
    /**
     * The admin panel door is role-gated: authenticated non-staff (participants)
     * belong in the member area. Guests pass through — the SPA shows its login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect()->route('member.area');
        }

        return $next($request);
    }
}
