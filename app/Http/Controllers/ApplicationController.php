<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionParticipantAccount;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Program;
use App\Support\ProgramEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Provinsi;

class ApplicationController extends Controller
{
    /** Show the application form (prefilled + honest for logged-in participants). */
    public function create(Request $request, Program $program): View|RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        if (! app(ProgramEligibility::class)->canAccess(Auth::user()?->person, $program)) {
            return redirect()->route('program.show', $program);
        }

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        if ($user) {
            abort_unless($user->person, 403);
        }

        // One active application per program: pending, accepted, and enrolled
        // all park the visitor on a status card instead of the form. The copy
        // per state lives here so the template stays declarative.
        $person = $user?->person;
        $applicationState = $person?->applicationStateFor($program) ?? 'none';
        $stateNotice = match ($applicationState) {
            'pending' => [
                'title' => 'Kamu sudah mendaftar program ini.',
                'body' => 'Pendaftaranmu sedang kami tinjau. Pantau statusnya di halaman akunmu.',
            ],
            'accepted' => [
                'title' => 'Kamu sudah diterima di program ini.',
                'body' => 'Selamat! Tim kami akan menghubungimu. Lihat detailnya di halaman akunmu.',
            ],
            'enrolled' => [
                'title' => 'Kamu peserta program ini.',
                'body' => 'Kamu sudah tergabung di Angkatan program ini. Lihat perjalananmu di halaman akunmu.',
            ],
            default => null,
        };

        // Guests answer "sudah punya akun?" FIRST; the form only appears after
        // they choose "belum" (?baru=1) or when they return from a validation
        // redirect (the view also falls through on errors).
        $showGate = $user === null && ! $request->boolean('baru');

        // Logged-in members CONFIRM their stored data instead of retyping it;
        // ?ubah=1 opens the editable form when something needs updating. A
        // profile predating the intake fields (no birth date yet) skips the
        // confirmation and completes the editable form directly.
        $confirming = $user !== null && ! $request->boolean('ubah') && $person?->birth_date !== null && $person->gender !== null && $person->followed_socials !== null;

        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('funnel.apply', compact('program', 'provinces', 'person', 'applicationState', 'stateNotice', 'showGate', 'confirming'));
    }

    /** JSON list of cities for a province — feeds the dependent dropdown. */
    public function cities(string $province): JsonResponse
    {
        $cities = Kabupaten::query()
            ->where('province_code', $province)
            ->orderBy('name')
            ->get(['code', 'name']);

        return response()->json($cities);
    }

    /**
     * Store a submission. Guests are provisioned a participant account (Person
     * + User, auto-logged in). Logged-in participants self-update their
     * identity instead. Either way, records a new pending Application unless
     * one already exists for this program.
     */
    public function store(StoreApplicationRequest $request, Program $program, ProvisionParticipantAccount $provisioner): RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        if (! app(ProgramEligibility::class)->canAccess(Auth::user()?->person, $program)) {
            return redirect()->route('program.show', $program);
        }

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $data = $request->validated();

        if ($user) {
            // Logged-in participant: their Person is authoritative; refresh it.
            abort_unless($user->person, 403);

            $person = $user->person;
            $person->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
                'tiktok_followers' => $data['tiktok_followers'] ?? null,
                'has_started_affiliate' => $data['has_started_affiliate'] ?? null,
                'affiliate_level' => $data['affiliate_level'] ?? null,
                'affiliate_gmv_range' => $data['affiliate_gmv_range'] ?? null,
                'followed_socials' => $data['followed_socials'],
            ]);
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
        } else {
            // Guest: provision the account (throws on an account-carrying phone).
            [$person, $account] = $provisioner->provision([
                'phone' => $data['phone'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $person->update([
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
                'tiktok_followers' => $data['tiktok_followers'] ?? null,
                'has_started_affiliate' => $data['has_started_affiliate'] ?? null,
                'affiliate_level' => $data['affiliate_level'] ?? null,
                'affiliate_gmv_range' => $data['affiliate_gmv_range'] ?? null,
                'followed_socials' => $data['followed_socials'],
            ]);

            Auth::login($account);
            $request->session()->regenerate();
        }

        // One ACTIVE application per program: pending/accepted/enrolled block a
        // new attempt; only a rejected one reopens the door (a recorded retry
        // is a feature, not redundancy). Honest response: back to the landing
        // page with a notice — never a fake thank-you.
        if (in_array($person->applicationStateFor($program), ['pending', 'accepted', 'enrolled'], true)) {
            return redirect()
                ->route('program.show', $program)
                ->with('application_notice', 'Kamu sudah terdaftar di program ini.');
        }

        // The application targets the class actually recruiting right now; the
        // program is the catalog grouping. store() is only reachable while the
        // program is open, so an open cohort always exists here.
        $person->applications()->create([
            'status' => 'pending',
            'program_id' => $program->id,
            'cohort_id' => $program->openCohort()?->id,
            'referral_source' => $data['referral_source'],
            'motivation' => $data['motivation'],
        ]);

        return redirect()
            ->route('daftar.thankyou')
            ->with('applicant_name', $person->name)
            ->with('has_account', true);
    }

    /** Confirmation page after a successful submission. */
    public function thankYou(): View
    {
        return view('terima-kasih');
    }
}
