<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            ['key' => 'attendance.absent', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.absent', 'channel' => 'email', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.present', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.present', 'channel' => 'email', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
        ];

        foreach ($updates as $row) {
            DB::table('notification_templates')
                ->where('key', $row['key'])
                ->where('channel', $row['channel'])
                ->update(['body' => $row['body'], 'updated_at' => now()]);
        }

        // Ensure present email template exists for older tenants.
        $tenantIds = DB::table('notification_templates')->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            $exists = DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('key', 'attendance.present')
                ->where('channel', 'email')
                ->exists();

            if (! $exists) {
                DB::table('notification_templates')->insert([
                    'tenant_id' => $tenantId,
                    'key' => 'attendance.present',
                    'channel' => 'email',
                    'locale' => 'en',
                    'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
