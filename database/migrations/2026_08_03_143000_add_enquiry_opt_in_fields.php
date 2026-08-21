<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_in')->default(false)->after('notes');
            $table->boolean('sms_opt_in')->default(false)->after('whatsapp_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_opt_in', 'sms_opt_in']);
        });
    }
};
