<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the base roles for the Kheedma Academy identity model.
     *
     *  - admin       : full access to the admin panel (the team)
     *  - mentor      : a team account assigned to lead cohorts
     *  - participant : a future login for accepted participants (Person.user_id)
     */
    public function run(): void
    {
        foreach (['admin', 'mentor', 'participant'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
