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

        $person->communityMembership()->firstOrCreate(
            [],
            ['referral_source' => $data['referral_source']]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('member.area')->with('joined', true);
    }
}
