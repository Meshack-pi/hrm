<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            FarmsSeeder::class,
            StatutoryRatesSeeder::class,
            LeaveTypesSeeder::class,
            PublicHolidaysSeeder::class,
            PayComponentsSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@yegenfarms.co.ke'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('Super Admin');

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
