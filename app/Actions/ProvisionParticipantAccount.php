<?php

namespace App\Actions;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProvisionParticipantAccount
{
    /**
     * Find-or-create the Person by phone (the identity anchor) and give them a
     * participant login. Shared by the community door and the program
     * application form. Callers handle login/session and any extras
     * (membership row, application row).
     *
     * @param  array{phone: string, name: string, email: string, password: string}  $identity
     * @return array{0: Person, 1: User}
     */
    public function provision(array $identity): array
    {
        $person = Person::firstOrNew(['phone' => $identity['phone']]);

        // The phone anchor already carries a login: this human has an account.
        if ($person->exists && $person->user_id !== null) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor ini sudah punya akun. Silakan masuk.',
            ]);
        }

        $user = DB::transaction(function () use ($person, $identity): User {
            $person->fill([
                'name' => $identity['name'],
                'email' => $identity['email'],
            ])->save();

            return $this->createParticipantLogin($person, $identity['password']);
        });

        return [$person->fresh(), $user];
    }

    /**
     * The well-known starter PIN for admin-provisioned accounts (PO decision
     * 2026-07-20): operationally simpler than relaying a random PIN — the
     * admin just tells the participant "PIN awalmu 123456".
     */
    public const DEFAULT_PIN = '123456';

    /**
     * Give an already-existing Person a participant login if they lack one,
     * using their stored name/email and the supplied 6-digit PIN (falls back
     * to DEFAULT_PIN). Used when an admin enrols someone directly (no funnel
     * application), so every enrolled participant can log in. Returns the
     * plain PIN to relay, or null when the person already had an account.
     * Idempotent. Call within the caller's transaction: it owns the
     * atomicity, so the enrolment rolls back with a failed provision.
     */
    public function ensureAccountFor(Person $person, ?string $pin = null): ?string
    {
        if ($person->user_id !== null) {
            return null;
        }

        $pin ??= self::DEFAULT_PIN;
        $this->createParticipantLogin($person, $pin);

        return $pin;
    }

    /** Create the participant User, assign the role, and link it to the Person. */
    private function createParticipantLogin(Person $person, string $plainPin): User
    {
        $user = User::create([
            'name' => $person->name,
            'email' => $person->email,
            'password' => Hash::make($plainPin),
            'is_active' => true,
        ]);
        $user->assignRole('participant');

        $person->user_id = $user->id;
        $person->save();

        return $user;
    }
}
