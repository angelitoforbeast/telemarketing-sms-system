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

        // ── Demo Company Manager ──
        $manager = \App\Models\User::firstOrCreate(
            ['email' => 'manager@demo.com'],
            [
                'name' => 'Demo Manager',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        $manager->assignRole('Company Manager');

        // ── 5 Demo Telemarketer Accounts ──
        $telemarketers = [
            ['email' => 'agent1@demo.com', 'name' => 'Maria Santos'],
            ['email' => 'agent2@demo.com', 'name' => 'Juan Cruz'],
            ['email' => 'agent3@demo.com', 'name' => 'Ana Reyes'],
            ['email' => 'agent4@demo.com', 'name' => 'Carlo Garcia'],
            ['email' => 'agent5@demo.com', 'name' => 'Liza Mendoza'],
        ];

        $createdTelemarketers = [];
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
            $createdTelemarketers[] = $tm;
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

        // ── Default Status Transition Rules ──
        $deliveredStatus = \App\Models\ShipmentStatus::where('code', 'delivered')->first();
        $failedDeliveryStatus = \App\Models\ShipmentStatus::where('code', 'failed_delivery')->first();
        $inTransitStatus = \App\Models\ShipmentStatus::where('code', 'in_transit')->first();
        $closedStatus = \App\Models\ShipmentStatus::where('code', 'closed')->first();
        $deliveringStatus = \App\Models\ShipmentStatus::where('code', 'delivering')->first();

        $transitionRules = [
            // For Return → Returned: reassign to Returned agent
            [
                'from_status_id' => $forReturnStatus?->id,
                'to_status_id' => $returnStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 0,
                'priority' => 10,
                'description' => 'When For Return becomes Returned, reassign to Returned agent',
            ],
            // Returned → For Return: reassign to For Return agent
            [
                'from_status_id' => $returnStatus?->id,
                'to_status_id' => $forReturnStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 0,
                'priority' => 10,
                'description' => 'When Returned becomes For Return, reassign to For Return agent',
            ],
            // For Return → Delivered: reassign with 7-day cooldown (reorder opportunity)
            [
                'from_status_id' => $forReturnStatus?->id,
                'to_status_id' => $deliveredStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 7,
                'priority' => 5,
                'description' => 'When For Return becomes Delivered, wait 7 days then assign for reorder call',
            ],
            // Returned → Delivered: reassign with 7-day cooldown (reorder opportunity)
            [
                'from_status_id' => $returnStatus?->id,
                'to_status_id' => $deliveredStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 7,
                'priority' => 5,
                'description' => 'When Returned becomes Delivered, wait 7 days then assign for reorder call',
            ],
            // Returned → In Transit: unassign (back in transit, no call needed)
            [
                'from_status_id' => $returnStatus?->id,
                'to_status_id' => $inTransitStatus?->id,
                'action' => 'auto_unassign',
                'reset_attempts' => false,
                'cooldown_days' => 0,
                'priority' => 8,
                'description' => 'When Returned goes back to In Transit, unassign (no call needed)',
            ],
            // Any → Closed: mark completed
            [
                'from_status_id' => null,
                'to_status_id' => $closedStatus?->id,
                'action' => 'mark_completed',
                'reset_attempts' => false,
                'cooldown_days' => 0,
                'priority' => 20,
                'description' => 'When any status becomes Closed, mark as completed (no more calls)',
            ],
            // Delivered → For Return: reassign to For Return agent
            [
                'from_status_id' => $deliveredStatus?->id,
                'to_status_id' => $forReturnStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 0,
                'priority' => 10,
                'description' => 'When Delivered becomes For Return, reassign to For Return agent',
            ],
            // Failed Delivery → For Return: reassign to For Return agent
            [
                'from_status_id' => $failedDeliveryStatus?->id,
                'to_status_id' => $forReturnStatus?->id,
                'action' => 'auto_reassign',
                'reset_attempts' => true,
                'cooldown_days' => 0,
                'priority' => 9,
                'description' => 'When Failed Delivery becomes For Return, reassign to For Return agent',
            ],
        ];

        foreach ($transitionRules as $rule) {
            // Skip rules where a required status wasn't found
            if (isset($rule['to_status_id']) && $rule['to_status_id'] === null) continue;

            \App\Models\StatusTransitionRule::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $rule['from_status_id'],
                    'to_status_id' => $rule['to_status_id'],
                ],
                array_merge($rule, [
                    'company_id' => $company->id,
                    'is_active' => true,
                ])
            );
        }

        // ── Demo Agent Status Assignments ──
        // Assign specific statuses to each telemarketer for demo purposes
        if (count($createdTelemarketers) >= 5 && $returnStatus && $forReturnStatus && $deliveredStatus) {
            // Agent 1 & 2: Returned shipments only
            \App\Models\TelemarketerStatusAssignment::firstOrCreate([
                'user_id' => $createdTelemarketers[0]->id,
                'company_id' => $company->id,
                'shipment_status_id' => $returnStatus->id,
            ]);
            \App\Models\TelemarketerStatusAssignment::firstOrCreate([
                'user_id' => $createdTelemarketers[1]->id,
                'company_id' => $company->id,
                'shipment_status_id' => $returnStatus->id,
            ]);

            // Agent 3 & 4: For Return shipments only
            \App\Models\TelemarketerStatusAssignment::firstOrCreate([
                'user_id' => $createdTelemarketers[2]->id,
                'company_id' => $company->id,
                'shipment_status_id' => $forReturnStatus->id,
            ]);
            \App\Models\TelemarketerStatusAssignment::firstOrCreate([
                'user_id' => $createdTelemarketers[3]->id,
                'company_id' => $company->id,
                'shipment_status_id' => $forReturnStatus->id,
            ]);

            // Agent 5: Delivered (Reorder) only
            \App\Models\TelemarketerStatusAssignment::firstOrCreate([
                'user_id' => $createdTelemarketers[4]->id,
                'company_id' => $company->id,
                'shipment_status_id' => $deliveredStatus->id,
            ]);
        }
    }
}
