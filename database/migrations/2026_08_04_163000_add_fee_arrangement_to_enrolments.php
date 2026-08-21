<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('fee_style')->nullable()->after('status'); // monthly|term|installments|custom
            $table->decimal('fee_amount', 12, 2)->nullable()->after('fee_style');
            $table->unsignedTinyInteger('fee_installments')->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['fee_style', 'fee_amount', 'fee_installments']);
        });
    }
};
