<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionParticipantAccount;
use App\Http\Requests\CommunityJoinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityController extends Controller
{
    /** Public join form for the affiliator community. */
    public function show(): View
    {
        return view('funnel.community');
    }

    /**
     * Join: find-or-create the Person by phone (the identity anchor), create
     * their participant account, record the membership, and sign them in.
     */
    public function join(CommunityJoinRequest $request, ProvisionParticipantAccount $provisioner): RedirectResponse
    {
        $data = $request->validated();

        [$person, $user] = $provisioner->provision([
            'phone' => $data['phone'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $person->update([
            'birth_date' => $data['birth_date'],
            'tiktok_followers' => $data['tiktok_followers'] ?? null,
            'has_started_affiliate' => $data['has_started_affiliate'] ?? null,
            'affiliate_level' => $data['affiliate_level'] ?? null,
            'affiliate_gmv_range' => $data['affiliate_gmv_range'] ?? null,
        ]);

        $person->communityMembership()->firstOrCreate(
            [],
            ['referral_source' => $data['referral_source'], 'motivation' => $data['motivation']]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('member.area')->with('joined', true);
    }
}
