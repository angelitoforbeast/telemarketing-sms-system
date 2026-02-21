<?php

namespace Database\Seeders;

use App\Models\ShipmentStatus;
use Illuminate\Database\Seeder;

class ShipmentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Picked Up',        'code' => 'picked_up',       'is_terminal' => false, 'is_sms_triggerable' => true,  'sort_order' => 1],
            ['name' => 'In Transit',        'code' => 'in_transit',      'is_terminal' => false, 'is_sms_triggerable' => true,  'sort_order' => 2],
            ['name' => 'Delivering',        'code' => 'delivering',      'is_terminal' => false, 'is_sms_triggerable' => true,  'sort_order' => 3],
            ['name' => 'Delivered',         'code' => 'delivered',       'is_terminal' => true,  'is_sms_triggerable' => false, 'sort_order' => 4],
            ['name' => 'Failed Delivery',   'code' => 'failed_delivery', 'is_terminal' => false, 'is_sms_triggerable' => true,  'sort_order' => 5],
            ['name' => 'For Return',        'code' => 'for_return',      'is_terminal' => false, 'is_sms_triggerable' => true,  'sort_order' => 6],
            ['name' => 'Returned',          'code' => 'returned',        'is_terminal' => true,  'is_sms_triggerable' => false, 'sort_order' => 7],
            ['name' => 'Closed',            'code' => 'closed',          'is_terminal' => true,  'is_sms_triggerable' => false, 'sort_order' => 8],
            ['name' => 'Unknown',           'code' => 'unknown',         'is_terminal' => false, 'is_sms_triggerable' => false, 'sort_order' => 99],
        ];

        foreach ($statuses as $status) {
            ShipmentStatus::updateOrCreate(
                ['code' => $status['code']],
                $status
            );
        }
    }
}
