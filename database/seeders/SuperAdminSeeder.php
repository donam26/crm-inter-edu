<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the default super-admin user.
     *
     * Idempotent: re-running will not duplicate the user nor re-assign role if already present.
     */
    public function run(): void
    {
        // Super-admin là role TOÀN CỤC → team context = null khi gán.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $user = User::firstOrCreate(
            ['email' => 'admin@inter-edu.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'branch_id' => null,
            ],
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }
}
