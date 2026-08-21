<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('timing');
            $table->time('starts_at')->nullable()->after('weekdays');
            $table->time('ends_at')->nullable()->after('starts_at');
            $table->string('shift', 32)->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['weekdays', 'starts_at', 'ends_at', 'shift']);
        });
    }
};
