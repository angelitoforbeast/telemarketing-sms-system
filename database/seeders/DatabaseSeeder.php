<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ShipmentStatusSeeder::class,
            TelemarketingDispositionSeeder::class,
        ]);

        // Create the default Platform Admin user
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@platform.com'],
            [
                'name' => 'Platform Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'company_id' => null,
                'is_active' => true,
            ]
        );
        $admin->assignRole('Platform Admin');

        // Create a demo company with a company owner for testing
        $company = \App\Models\Company::firstOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'Demo Company',
                'status' => 'active',
                'contact_email' => 'demo@company.com',
            ]
        );

        $owner = \App\Models\User::firstOrCreate(
            ['email' => 'owner@demo.com'],
            [
                'name' => 'Demo Owner',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        $owner->assignRole('Company Owner');

        $telemarketer = \App\Models\User::firstOrCreate(
            ['email' => 'agent@demo.com'],
            [
                'name' => 'Demo Agent',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        $telemarketer->assignRole('Telemarketer');
    }
}
