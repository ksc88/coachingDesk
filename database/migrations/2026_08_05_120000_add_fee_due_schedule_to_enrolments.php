<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->unsignedTinyInteger('fee_due_day')->nullable()->after('fee_installments');
            $table->date('fee_first_due_date')->nullable()->after('fee_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['fee_due_day', 'fee_first_due_date']);
        });
    }
};
