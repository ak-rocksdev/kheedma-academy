<?php

namespace App\Http\Controllers;

use App\Models\Person;
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

        // Card chips carry the visitor's own relation to each program, so the
        // catalog answers "di mana posisiku?" before any click.
        $programs = Program::openForRegistration()->where('type', 'general')->latest()->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'chip' => $this->stateChip($person, $program),
            ]);

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
                'chip' => $this->stateChip($person, $program),
            ]);

        return view('funnel.chooser', compact('programs', 'affiliate'));
    }

    /** Program promo landing. Locked affiliate classes render as a teaser. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        $person = Auth::user()?->person;
        $isOpen = $program->isOpen();
        $locked = ! $this->eligibility->canAccess($person, $program);
        $applicationState = $person?->applicationStateFor($program) ?? 'none';

        // Public class list: the visitor learns the batch contains many
        // classes before registering. Guest-safe by construction — only
        // title/schedule/type are mapped; venue and links never leave here.
        $openCohort = $isOpen ? $program->openCohort() : null;
        $openClasses = $openCohort
            ? $openCohort->sessions()
                ->orderByRaw('scheduled_at IS NULL, scheduled_at')
                ->get()
                ->map(fn ($session) => [
                    'title' => $session->title,
                    'schedule' => $session->scheduledLabel(),
                    'is_online' => $session->isOnline(),
                ])->all()
            : [];

        return view('funnel.program', [
            'program' => $program,
            'sections' => $program->sections,
            'isOpen' => $isOpen,
            'openCohort' => $openCohort,
            'openClasses' => $openClasses,
            'locked' => $locked,
            'lockedMessage' => $program->locked_message ?? config('kheedma.default_locked_message'),
            'lockReason' => $this->eligibility->lockReason($person, $program),
            'applicationState' => $applicationState,
            'statePill' => $this->statePill($applicationState),
        ]);
    }

    /**
     * Small status chip for catalog cards; null when there is nothing to say.
     *
     * @return array{label: string, class: string}|null
     */
    private function stateChip(?Person $person, Program $program): ?array
    {
        return match ($person?->applicationStateFor($program)) {
            'pending' => ['label' => 'Terdaftar · menunggu review', 'class' => 'bg-orange-100 text-orange-700'],
            'accepted' => ['label' => 'Diterima', 'class' => 'bg-teal-100 text-teal-700'],
            'enrolled' => ['label' => 'Peserta', 'class' => 'bg-teal-700 text-white'],
            default => null,
        };
    }

    /**
     * Landing CTA replacement when the visitor already has an active relation.
     *
     * @return array{label: string, class: string}|null
     */
    private function statePill(string $applicationState): ?array
    {
        return match ($applicationState) {
            'pending' => ['label' => 'Pendaftaranmu sedang direview', 'class' => 'bg-orange-100 text-orange-800'],
            'accepted' => ['label' => 'Kamu sudah diterima di program ini', 'class' => 'bg-teal-100 text-teal-800'],
            'enrolled' => ['label' => 'Kamu peserta program ini', 'class' => 'bg-teal-700 text-white'],
            default => null,
        };
    }
}
