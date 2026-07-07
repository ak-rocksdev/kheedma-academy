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

            $user = User::create([
                'name' => $identity['name'],
                'email' => $identity['email'],
                'password' => Hash::make($identity['password']),
                'is_active' => true,
            ]);
            $user->assignRole('participant');

            $person->user_id = $user->id;
            $person->save();

            return $user;
        });

        return [$person->fresh(), $user];
    }
}
