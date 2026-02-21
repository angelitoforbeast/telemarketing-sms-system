<?php

namespace Database\Seeders;

use App\Models\TelemarketingDisposition;
use Illuminate\Database\Seeder;

class TelemarketingDispositionSeeder extends Seeder
{
    public function run(): void
    {
        $dispositions = [
            [
                'name' => 'Answered - Will Accept',
                'code' => 'answered_accept',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 1,
                'color' => 'green',
                'description' => 'Customer confirmed they will accept the delivery',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Answered - Request Redeliver',
                'code' => 'answered_redeliver',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 2,
                'color' => 'blue',
                'description' => 'Customer wants the parcel redelivered',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Answered - Refused / RTS',
                'code' => 'answered_refused',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 3,
                'color' => 'red',
                'description' => 'Customer refused the delivery, return to sender',
                'requires_callback' => false,
                'marks_do_not_call' => true,
            ],
            [
                'name' => 'Answered - Callback Requested',
                'code' => 'answered_callback',
                'is_final' => false,
                'is_system' => true,
                'sort_order' => 4,
                'color' => 'orange',
                'description' => 'Customer asked to be called back at a later time',
                'requires_callback' => true,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Answered - Reorder Interest',
                'code' => 'answered_reorder',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 5,
                'color' => 'emerald',
                'description' => 'Customer expressed interest in reordering',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'No Answer',
                'code' => 'no_answer',
                'is_final' => false,
                'is_system' => true,
                'sort_order' => 6,
                'color' => 'yellow',
                'description' => 'No one picked up the call',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Busy',
                'code' => 'busy',
                'is_final' => false,
                'is_system' => true,
                'sort_order' => 7,
                'color' => 'yellow',
                'description' => 'Line was busy',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Wrong Number',
                'code' => 'wrong_number',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 8,
                'color' => 'red',
                'description' => 'Phone number is incorrect or belongs to someone else',
                'requires_callback' => false,
                'marks_do_not_call' => true,
            ],
            [
                'name' => 'Not in Service',
                'code' => 'not_in_service',
                'is_final' => true,
                'is_system' => true,
                'sort_order' => 9,
                'color' => 'red',
                'description' => 'Phone number is no longer in service',
                'requires_callback' => false,
                'marks_do_not_call' => true,
            ],
            [
                'name' => 'Voicemail',
                'code' => 'voicemail',
                'is_final' => false,
                'is_system' => true,
                'sort_order' => 10,
                'color' => 'purple',
                'description' => 'Call went to voicemail',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
            [
                'name' => 'Other',
                'code' => 'other',
                'is_final' => false,
                'is_system' => true,
                'sort_order' => 99,
                'color' => 'gray',
                'description' => 'Other outcome not listed above',
                'requires_callback' => false,
                'marks_do_not_call' => false,
            ],
        ];

        foreach ($dispositions as $disposition) {
            TelemarketingDisposition::updateOrCreate(
                ['code' => $disposition['code'], 'company_id' => null],
                array_merge($disposition, ['company_id' => null])
            );
        }
    }
}
