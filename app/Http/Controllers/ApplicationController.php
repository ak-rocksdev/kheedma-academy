<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionParticipantAccount;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Provinsi;

class ApplicationController extends Controller
{
    /** Show the application form (prefilled + honest for logged-in participants). */
    public function create(Program $program): View|RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        if ($user) {
            abort_unless($user->person, 403);
        }

        $person = $user?->person;
        $pendingApplication = $person
            ? $person->applications()->where('program_id', $program->id)->where('status', 'pending')->exists()
            : false;

        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('funnel.apply', compact('program', 'provinces', 'person', 'pendingApplication'));
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
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
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
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
            ]);

            Auth::login($account);
            $request->session()->regenerate();
        }

        // One pending application per person per program (silent backstop).
        $alreadyPending = $person->applications()
            ->where('program_id', $program->id)
            ->where('status', 'pending')
            ->exists();

        if (! $alreadyPending) {
            $person->applications()->create([
                'status' => 'pending',
                'program_id' => $program->id,
                'referral_source' => $data['referral_source'],
            ]);
        }

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
