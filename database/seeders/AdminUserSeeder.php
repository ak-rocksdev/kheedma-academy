<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial admin account. Credentials come from env when provided
     * (ADMIN_EMAIL / ADMIN_PASSWORD) so production can set a real password;
     * otherwise a local-dev default is used. Idempotent.
     */
    public function run(): void
    {
        Role::findOrCreate('admin', 'web');

        $email = env('ADMIN_EMAIL', 'admin@kheedma.id');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ]
        );

        $user->syncRoles(['admin']);
    }
}
