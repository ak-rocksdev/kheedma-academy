<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Support\ProgramEligibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    public function __construct(private readonly ProgramEligibility $eligibility) {}

    /** Two-section chooser: open general programs, then the affiliate ladder. */
    public function chooser(): View
    {
        $person = Auth::user()?->person;

        $programs = Program::openForRegistration()->where('type', 'general')->latest()->get();

        // Affiliate classes are ALWAYS listed while active (teaser value),
        // locked or not, ordered by level.
        $affiliate = Program::query()
            ->where('status', 'active')
            ->where('type', 'affiliate_community')
            ->orderBy('level')
            ->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'locked' => ! $this->eligibility->canAccess($person, $program),
                'reason' => $this->eligibility->lockReason($person, $program),
                'message' => $program->locked_message ?? config('kheedma.default_locked_message'),
            ]);

        return view('funnel.chooser', compact('programs', 'affiliate'));
    }

    /** Program promo landing. Locked affiliate classes render as a teaser. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        $isOpen = $program->isOpen();
        $locked = ! $this->eligibility->canAccess(Auth::user()?->person, $program);

        return view('funnel.program', [
            'program' => $program,
            'isOpen' => $isOpen,
            'openCohort' => $isOpen ? $program->openCohort() : null,
            'locked' => $locked,
            'lockedMessage' => $program->locked_message ?? config('kheedma.default_locked_message'),
            'lockReason' => $this->eligibility->lockReason(Auth::user()?->person, $program),
        ]);
    }
}
