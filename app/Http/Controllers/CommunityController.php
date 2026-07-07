<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommunityJoinRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
    public function join(CommunityJoinRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $person = Person::firstOrNew(['phone' => $data['phone']]);

        // The phone anchor already carries a login: this human has an account.
        if ($person->exists && $person->user_id !== null) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor ini sudah punya akun. Silakan masuk.',
            ]);
        }

        $user = DB::transaction(function () use ($person, $data): User {
            $person->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ])->save();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->assignRole('participant');

            $person->user_id = $user->id;
            $person->save();

            $person->communityMembership()->firstOrCreate(
                [],
                ['referral_source' => $data['referral_source']]
            );

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('member.area')->with('joined', true);
    }
}
