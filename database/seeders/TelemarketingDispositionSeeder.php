<?php

namespace Database\Seeders;

use App\Models\TelemarketingDisposition;
use Illuminate\Database\Seeder;

class TelemarketingDispositionSeeder extends Seeder
{
    public function run(): void
    {
        $dispositions = [
            ['name' => 'Answered - Will Accept',       'code' => 'answered_accept',      'is_final' => true,  'is_system' => true, 'sort_order' => 1],
            ['name' => 'Answered - Refused',            'code' => 'answered_refused',     'is_final' => true,  'is_system' => true, 'sort_order' => 2],
            ['name' => 'Answered - Callback Requested', 'code' => 'answered_callback',    'is_final' => false, 'is_system' => true, 'sort_order' => 3],
            ['name' => 'No Answer',                     'code' => 'no_answer',            'is_final' => false, 'is_system' => true, 'sort_order' => 4],
            ['name' => 'Busy',                          'code' => 'busy',                 'is_final' => false, 'is_system' => true, 'sort_order' => 5],
            ['name' => 'Wrong Number',                  'code' => 'wrong_number',         'is_final' => true,  'is_system' => true, 'sort_order' => 6],
            ['name' => 'Not in Service',                'code' => 'not_in_service',       'is_final' => true,  'is_system' => true, 'sort_order' => 7],
            ['name' => 'Voicemail',                     'code' => 'voicemail',            'is_final' => false, 'is_system' => true, 'sort_order' => 8],
            ['name' => 'Other',                         'code' => 'other',                'is_final' => false, 'is_system' => true, 'sort_order' => 99],
        ];

        foreach ($dispositions as $disposition) {
            TelemarketingDisposition::updateOrCreate(
                ['code' => $disposition['code'], 'company_id' => null],
                array_merge($disposition, ['company_id' => null])
            );
        }
    }
}
