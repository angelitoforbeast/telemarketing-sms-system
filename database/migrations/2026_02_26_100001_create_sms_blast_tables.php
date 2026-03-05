<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SMS Devices - registered phones that can send SMS
        Schema::create('sms_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id')->nullable(); // SMS Operator user
            $table->string('device_name'); // e.g. "Phone A - Globe"
            $table->string('device_token', 64)->unique(); // unique token for API auth
            $table->string('sim_number')->nullable();
            $table->string('carrier')->nullable(); // Globe, Smart, TNT, DITO
            $table->unsignedInteger('daily_limit')->default(200); // safe daily limit per SIM
            $table->unsignedInteger('messages_sent_today')->default(0);
            $table->unsignedInteger('throttle_delay_seconds')->default(10); // delay between sends
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Update sms_campaigns - add scheduling and sending method fields
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->string('schedule_type')->default('immediate')->after('city_filter');
            // immediate, scheduled, recurring_daily, recurring_hourly, custom_cron
            $table->dateTime('scheduled_at')->nullable()->after('schedule_type');
            $table->string('recurring_time', 5)->nullable()->after('scheduled_at'); // HH:MM for daily
            $table->unsignedInteger('recurring_interval_hours')->nullable()->after('recurring_time');
            $table->string('cron_expression')->nullable()->after('recurring_interval_hours');
            $table->string('sending_method')->default('sim_based')->after('cron_expression');
            // sim_based, gateway
            $table->unsignedInteger('throttle_delay_seconds')->default(10)->after('sending_method');
            $table->string('recipient_filter_type')->default('dynamic')->after('throttle_delay_seconds');
            // dynamic, fixed
            $table->json('recipient_filters')->nullable()->after('recipient_filter_type');
            // For dynamic: {"statuses": [6,5], "couriers": ["JNT"], "date_range_days": 7, "exclude_already_sent": true}
            $table->string('campaign_status')->default('draft')->after('recipient_filters');
            // draft, queued, sending, paused, completed, cancelled
            $table->unsignedInteger('total_recipients')->default(0)->after('campaign_status');
            $table->unsignedInteger('total_sent')->default(0)->after('total_recipients');
            $table->unsignedInteger('total_failed')->default(0)->after('total_sent');
            $table->dateTime('last_run_at')->nullable()->after('total_failed');
            $table->dateTime('next_run_at')->nullable()->after('last_run_at');
        });

        // 3. Update sms_send_logs - add device tracking
        Schema::table('sms_send_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('campaign_id');
            $table->foreign('device_id')->references('id')->on('sms_devices')->onDelete('set null');
        });

        // 4. Add new SMS permissions
        $permissions = [
            'sms.devices.view',
            'sms.devices.manage',
            'sms.blast.send', // permission to actually send via phone
        ];

        $guardName = 'web';
        foreach ($permissions as $perm) {
            \DB::table('permissions')->insertOrIgnore([
                'name' => $perm,
                'guard_name' => $guardName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign new permissions to relevant roles
        $permIds = \DB::table('permissions')
            ->whereIn('name', $permissions)
            ->pluck('id', 'name');

        // Roles that get device management: Platform Admin, CEO, Company Owner, Company Manager
        $managerRoles = \DB::table('roles')
            ->whereIn('name', ['Platform Admin', 'CEO', 'Company Owner', 'Company Manager'])
            ->pluck('id');

        foreach ($managerRoles as $roleId) {
            foreach ($permIds as $permName => $permId) {
                \DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // SMS Operator gets blast.send and devices.view
        $smsOperatorRole = \DB::table('roles')->where('name', 'SMS Operator')->first();
        if ($smsOperatorRole) {
            foreach (['sms.blast.send', 'sms.devices.view'] as $perm) {
                if (isset($permIds[$perm])) {
                    \DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permIds[$perm],
                        'role_id' => $smsOperatorRole->id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('sms_send_logs', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn('device_id');
        });

        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type', 'scheduled_at', 'recurring_time',
                'recurring_interval_hours', 'cron_expression', 'sending_method',
                'throttle_delay_seconds', 'recipient_filter_type', 'recipient_filters',
                'campaign_status', 'total_recipients', 'total_sent', 'total_failed',
                'last_run_at', 'next_run_at',
            ]);
        });

        Schema::dropIfExists('sms_devices');
    }
};
