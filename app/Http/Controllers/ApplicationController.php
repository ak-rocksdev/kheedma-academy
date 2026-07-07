<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Person;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Provinsi;

class ApplicationController extends Controller
{
    /** Show the application form for one program (draft programs 404 via guard). */
    public function create(Program $program): View|RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('funnel.apply', compact('program', 'provinces'));
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
     * Store a submission. Matches an existing Person by phone (reconnecting a
     * returning applicant to their history) or creates one, refreshing their
     * latest details, then records a new pending Application.
     */
    public function store(StoreApplicationRequest $request, Program $program): RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $data = $request->validated();

        $person = Person::updateOrCreate(
            ['phone' => $data['phone']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
            ]
        );

        // One pending application per person per program: a re-submit while the
        // first is still in review is deduplicated silently (same thank-you page,
        // so the endpoint never reveals whether a phone number already applied).
        // A rejected applicant CAN apply again — that history is the point.
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
            ->with('applicant_name', $person->name);
    }

    /** Confirmation page after a successful submission. */
    public function thankYou(): View
    {
        return view('terima-kasih');
    }
}
