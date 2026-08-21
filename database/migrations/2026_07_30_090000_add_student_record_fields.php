<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('class_level', 32)->nullable()->after('last_name');
            $table->string('school_name', 191)->nullable()->after('class_level');
            $table->string('target_exam_year', 16)->nullable()->after('school_name');
            $table->string('source', 64)->nullable()->after('address');
            $table->text('remarks')->nullable()->after('source');
            $table->index(['tenant_id', 'class_level']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('occupation', 100)->nullable()->after('relation');
            $table->string('alternate_phone', 20)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'class_level']);
            $table->dropColumn(['class_level', 'school_name', 'target_exam_year', 'source', 'remarks']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'alternate_phone']);
        });
    }
};
