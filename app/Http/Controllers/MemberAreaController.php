<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Program;
use App\Support\ProgramEligibility;
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

        $person = $user->person()->with([
            'communityMembership',
            'applications' => fn ($q) => $q->latest(),
            'applications.program:id,name',
            'applications.cohort:id,name,start_date',
        ])->first();

        $eligibility = app(ProgramEligibility::class);
        $affiliate = Program::query()
            ->where('status', 'active')
            ->where('type', 'affiliate_community')
            ->orderBy('level')
            ->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'locked' => ! $eligibility->canAccess($person, $program),
                'reason' => $eligibility->lockReason($person, $program),
                'message' => $program->locked_message ?? config('kheedma.default_locked_message'),
            ]);

        // General classes currently open for registration: the member-area door
        // into the same funnel form (which short-circuits to a confirm card for
        // logged-in members). An active relation replaces the CTA with a chip.
        $openClasses = Program::query()
            ->openForRegistration()
            ->where('type', 'general')
            ->latest()
            ->get()
            ->map(function (Program $program) use ($person) {
                $state = $person?->applicationStateFor($program) ?? 'none';

                return [
                    'program' => $program,
                    'openCohort' => $program->openCohort(),
                    'state' => $state,
                    'chip' => $this->stateChip($state),
                ];
            });

        // Tab aktif dari query ?bagian=; nilai tak dikenal jatuh ke default.
        $activeTab = in_array($request->query('bagian'), ['kelas', 'profil'], true)
            ? $request->query('bagian')
            : 'pendaftaran';

        return view('member.akun', [
            'user' => $user,
            'person' => $person,
            'membership' => $person?->communityMembership,
            'applications' => ($person?->applications ?? collect())->map(fn ($a) => $this->applicationCard($a)),
            'affiliate' => $affiliate,
            'openClasses' => $openClasses,
            'activeTab' => $activeTab,
        ]);
    }

    /** Chip text for an open-class card; null = no active relation, show the CTA. */
    private function stateChip(string $state): ?string
    {
        return match ($state) {
            'pending' => 'Menunggu review',
            'accepted' => 'Kamu diterima',
            'enrolled' => 'Kamu peserta',
            default => null,
        };
    }

    /**
     * Presentation of one application row on the status card (label + badge
     * classes computed here, not in the Blade).
     *
     * @return array<string, mixed>
     */
    private function applicationCard(Application $application): array
    {
        return [
            'program' => $application->program?->name ?? 'Program',
            'cohort' => $application->cohort?->name,
            'created_at' => $application->created_at,
            'status' => $application->status,
            'statusLabel' => match ($application->status) {
                'pending' => 'Menunggu',
                'accepted' => 'Diterima',
                'rejected' => 'Belum lolos',
                default => $application->status,
            },
            'statusClass' => match ($application->status) {
                'pending' => 'bg-orange-100 text-orange-700',
                'accepted' => 'bg-teal-100 text-teal-700',
                'rejected' => 'bg-red-50 text-red-600',
                default => 'bg-sand-100 text-teal-800/70',
            },
            'reviewNote' => $application->status === 'rejected' ? $application->review_note : null,
        ];
    }
}
