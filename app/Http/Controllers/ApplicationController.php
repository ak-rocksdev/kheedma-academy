<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Provinsi;

class ApplicationController extends Controller
{
    /** Show the public application form. */
    public function create(): View
    {
        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('daftar', compact('provinces'));
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
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
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

        $person->applications()->create(['status' => 'pending']);

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
