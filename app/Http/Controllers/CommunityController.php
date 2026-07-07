<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionParticipantAccount;
use App\Http\Requests\CommunityJoinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityController extends Controller
{
    /** Show the join form (prefilled + honest for logged-in participants). */
    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        if ($user) {
            abort_unless($user->person, 403);
        }

        $person = $user?->person;
        $alreadyMember = (bool) $person?->communityMembership;

        // Logged-in members CONFIRM their stored data instead of retyping it;
        // ?ubah=1 opens the editable form when something needs updating. A
        // profile predating the intake fields (no birth date yet) skips the
        // confirmation and completes the editable form directly.
        $confirming = $user !== null && ! $request->boolean('ubah') && $person?->birth_date !== null && $person->gender !== null && $person->followed_socials !== null;

        // Members editing their data (?ubah=1) get a focused page: the intro
        // story is hidden so the form is front and center.
        $focusedEdit = $user !== null && $request->boolean('ubah');

        return view('funnel.community', compact('person', 'alreadyMember', 'confirming', 'focusedEdit'));
    }

    /**
     * Join: find-or-create the Person by phone (the identity anchor), create
     * their participant account, record the membership, and sign them in.
     * Logged-in participants self-update their identity instead of being
     * provisioned a second time.
     */
    public function join(CommunityJoinRequest $request, ProvisionParticipantAccount $provisioner): RedirectResponse
    {
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
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
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
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'tiktok_followers' => $data['tiktok_followers'] ?? null,
                'has_started_affiliate' => $data['has_started_affiliate'] ?? null,
                'affiliate_level' => $data['affiliate_level'] ?? null,
                'affiliate_gmv_range' => $data['affiliate_gmv_range'] ?? null,
                'followed_socials' => $data['followed_socials'],
            ]);

            Auth::login($account);
            $request->session()->regenerate();
        }

        $person->communityMembership()->firstOrCreate(
            [],
            ['referral_source' => $data['referral_source'], 'motivation' => $data['motivation']]
        );

        return redirect()->route('member.area')->with('joined', true);
    }
}
