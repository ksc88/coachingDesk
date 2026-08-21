<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stronger uniqueness + lookup indexes for multi-tenant coaching data.
 * Safe on MySQL; skips indexes that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One current academic year label per coaching.
        $this->addUniqueIfMissing('academic_sessions', 'academic_sessions_tenant_name_unique', ['tenant_id', 'name']);

        // Course / subject / batch identity within a coaching.
        $this->addUniqueIfMissing('courses', 'courses_tenant_name_unique', ['tenant_id', 'name']);
        $this->addUniqueIfMissing('subjects', 'subjects_tenant_name_unique', ['tenant_id', 'name']);
        $this->addUniqueIfMissing('batches', 'batches_tenant_session_name_unique', ['tenant_id', 'academic_session_id', 'name']);

        // Status / filter helpers.
        $this->addIndexIfMissing('tenants', 'tenants_status_index', ['status']);
        $this->addIndexIfMissing('users', 'users_tenant_active_index', ['tenant_id', 'is_active']);
        $this->addIndexIfMissing('batches', 'batches_tenant_active_index', ['tenant_id', 'is_active']);
        $this->addIndexIfMissing('students', 'students_tenant_status_index', ['tenant_id', 'status']);
        $this->addIndexIfMissing('enrolments', 'enrolments_tenant_status_index', ['tenant_id', 'status']);
        $this->addIndexIfMissing('academic_sessions', 'academic_sessions_tenant_current_index', ['tenant_id', 'is_current']);

        // Receipt / invoice lookup by financial year.
        $this->addIndexIfMissing('receipts', 'receipts_tenant_fy_index', ['tenant_id', 'financial_year']);
        $this->addIndexIfMissing('invoices', 'invoices_tenant_status_index', ['tenant_id', 'status']);

        // Prevent one guardian phone from being duplicated inside the same coaching.
        $this->addUniqueIfMissing('guardians', 'guardians_tenant_phone_unique', ['tenant_id', 'phone']);

        // Staff assignment: same teacher shouldn't be assigned twice to the same batch+subject.
        $this->addUniqueIfMissing('staff_assignments', 'staff_assignments_unique', ['tenant_id', 'user_id', 'batch_id', 'subject_id']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // MySQL CHECK constraints (Laravel 10+/MySQL 8.0.16+).
            $this->addCheckIfMissing('tenants', 'tenants_status_check', "status in ('pending','active','suspended','closed')");
            $this->addCheckIfMissing('students', 'students_status_check', "status in ('active','left','alumni','inactive')");
            $this->addCheckIfMissing('students', 'students_gender_check', "gender is null or gender in ('male','female','other')");
            $this->addCheckIfMissing('enrolments', 'enrolments_status_check', "status in ('active','completed','left','transferred')");
            $this->addCheckIfMissing('batches', 'batches_fee_non_negative', 'default_fee >= 0');
            $this->addCheckIfMissing('academic_sessions', 'academic_sessions_date_order', 'ends_on >= starts_on');
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('academic_sessions', 'academic_sessions_tenant_name_unique');
        $this->dropIndexIfExists('courses', 'courses_tenant_name_unique');
        $this->dropIndexIfExists('subjects', 'subjects_tenant_name_unique');
        $this->dropIndexIfExists('batches', 'batches_tenant_session_name_unique');
        $this->dropIndexIfExists('tenants', 'tenants_status_index');
        $this->dropIndexIfExists('users', 'users_tenant_active_index');
        $this->dropIndexIfExists('batches', 'batches_tenant_active_index');
        $this->dropIndexIfExists('students', 'students_tenant_status_index');
        $this->dropIndexIfExists('enrolments', 'enrolments_tenant_status_index');
        $this->dropIndexIfExists('academic_sessions', 'academic_sessions_tenant_current_index');
        $this->dropIndexIfExists('receipts', 'receipts_tenant_fy_index');
        $this->dropIndexIfExists('invoices', 'invoices_tenant_status_index');
        $this->dropIndexIfExists('guardians', 'guardians_tenant_phone_unique');
        $this->dropIndexIfExists('staff_assignments', 'staff_assignments_unique');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            foreach ([
                'tenants' => 'tenants_status_check',
                'students' => ['students_status_check', 'students_gender_check'],
                'enrolments' => 'enrolments_status_check',
                'batches' => 'batches_fee_non_negative',
                'academic_sessions' => 'academic_sessions_date_order',
            ] as $table => $checks) {
                foreach ((array) $checks as $check) {
                    DB::statement("alter table `{$table}` drop check `{$check}`");
                }
            }
        }
    }

    protected function addUniqueIfMissing(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->unique($columns, $name);
        });
    }

    protected function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    protected function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    protected function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    protected function addCheckIfMissing(string $table, string $name, string $expression): void
    {
        $exists = DB::selectOne(
            'select 1 as ok from information_schema.table_constraints where table_schema = database() and table_name = ? and constraint_name = ? and constraint_type = ? limit 1',
            [$table, $name, 'CHECK']
        );

        if ($exists) {
            return;
        }

        DB::statement("alter table `{$table}` add constraint `{$name}` check ({$expression})");
    }
};
