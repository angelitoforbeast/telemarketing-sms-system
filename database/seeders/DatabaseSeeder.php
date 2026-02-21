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

        $ceo = \App\Models\User::firstOrCreate(
            ['email' => 'ceo@demo.com'],
            [
                'name' => 'Demo CEO',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        $ceo->assignRole('CEO');

        // ── 5 Demo Telemarketer Accounts ──
        $telemarketers = [
            ['email' => 'agent1@demo.com', 'name' => 'Maria Santos'],
            ['email' => 'agent2@demo.com', 'name' => 'Juan Cruz'],
            ['email' => 'agent3@demo.com', 'name' => 'Ana Reyes'],
            ['email' => 'agent4@demo.com', 'name' => 'Carlo Garcia'],
            ['email' => 'agent5@demo.com', 'name' => 'Liza Mendoza'],
        ];

        foreach ($telemarketers as $tmData) {
            $tm = \App\Models\User::firstOrCreate(
                ['email' => $tmData['email']],
                [
                    'name' => $tmData['name'],
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'company_id' => $company->id,
                    'is_active' => true,
                ]
            );
            $tm->assignRole('Telemarketer');
        }

        // ── Default Assignment Rules ──
        $returnStatus = \App\Models\ShipmentStatus::where('code', 'returned')->first();
        $forReturnStatus = \App\Models\ShipmentStatus::where('code', 'for_return')->first();

        if ($returnStatus) {
            \App\Models\TelemarketingAssignmentRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Return Shipments'],
                [
                    'rule_type' => 'status_based',
                    'status_id' => $returnStatus->id,
                    'assignment_method' => 'round_robin',
                    'is_active' => true,
                    'priority' => 10,
                    'max_attempts' => 5,
                ]
            );
        }

        if ($forReturnStatus) {
            \App\Models\TelemarketingAssignmentRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'For Return Shipments'],
                [
                    'rule_type' => 'status_based',
                    'status_id' => $forReturnStatus->id,
                    'assignment_method' => 'round_robin',
                    'is_active' => true,
                    'priority' => 9,
                    'max_attempts' => 5,
                ]
            );
        }

        \App\Models\TelemarketingAssignmentRule::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Delivered 7+ Days (Reorder)'],
            [
                'rule_type' => 'delivered_age',
                'days_threshold' => 7,
                'assignment_method' => 'workload_based',
                'is_active' => true,
                'priority' => 5,
                'max_attempts' => 3,
            ]
        );
    }
}
