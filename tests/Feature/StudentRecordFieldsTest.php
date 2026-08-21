<?php

namespace Tests\Feature;

use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentRecordFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->owner = app(TenantProvisioner::class)->provision([
            'name' => 'Record Coaching',
            'code' => 'REC',
            'slug' => 'record-coaching',
            'owner_name' => 'Rec Owner',
            'owner_email' => 'owner@rec.test',
            'password' => 'secret-pass-123',
        ])['owner'];
    }

    public function test_admission_form_stores_the_full_student_record(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/students', [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'class_level' => 'XII',
                'school_name' => 'Kendriya Vidyalaya',
                'target_exam_year' => '2027',
                'date_of_birth' => '2009-05-14',
                'gender' => 'male',
                'phone' => '9876500011',
                'email' => 'aarav@example.test',
                'address' => 'Civil Lines, Kannauj',
                'source' => 'Referral',
                'remarks' => 'Strong in physics',
                'guardian_name' => 'Rakesh Sharma',
                'guardian_relation' => 'father',
                'guardian_occupation' => 'Shopkeeper',
                'guardian_phone' => '9876500012',
                'guardian_alternate_phone' => '9876500013',
                'guardian_email' => 'rakesh@example.test',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
            ])
            ->assertRedirect();

        $student = Student::query()->withoutGlobalScope('tenant')->firstOrFail();

        $this->assertSame('XII', $student->class_level);
        $this->assertSame('Kendriya Vidyalaya', $student->school_name);
        $this->assertSame('2027', $student->target_exam_year);
        $this->assertSame('2009-05-14', $student->date_of_birth->toDateString());
        $this->assertSame('male', $student->gender);
        $this->assertSame('Referral', $student->source);
        $this->assertSame('Strong in physics', $student->remarks);

        $guardian = Guardian::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('father', $guardian->relation);
        $this->assertSame('Shopkeeper', $guardian->occupation);
        $this->assertSame('9876500013', $guardian->alternate_phone);
        $this->assertTrue($guardian->whatsapp_opt_in);
        $this->assertTrue($guardian->sms_opt_in);
    }

    public function test_guardian_opt_ins_can_be_turned_off_on_update(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/students', [
                'first_name' => 'Opt',
                'last_name' => 'Out',
                'guardian_name' => 'Parent Opt',
                'guardian_phone' => '9876500099',
                'guardian_email' => 'parent-opt@example.test',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
            ])
            ->assertRedirect();

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Opt')->firstOrFail();

        $this->actingAs($this->owner)
            ->put("/app/students/{$student->id}", [
                'first_name' => 'Opt',
                'last_name' => 'Out',
                'guardian_name' => 'Parent Opt',
                'guardian_phone' => '9876500099',
                'guardian_email' => 'parent-opt@example.test',
                'whatsapp_opt_in' => false,
                'sms_opt_in' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guardian = Guardian::query()->withoutGlobalScope('tenant')->where('name', 'Parent Opt')->firstOrFail();
        $this->assertFalse($guardian->whatsapp_opt_in);
        $this->assertFalse($guardian->sms_opt_in);
        $this->assertTrue($guardian->email_opt_in);
    }

    public function test_guardian_email_saves_when_editing_existing_parent(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/students', [
                'first_name' => 'Mail',
                'last_name' => 'Kid',
                'guardian_name' => 'Father Mail',
                'guardian_phone' => '9876500088',
                'guardian_email' => 'old@example.test',
                'whatsapp_opt_in' => false,
                'sms_opt_in' => false,
            ])
            ->assertRedirect();

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Mail')->firstOrFail();

        $this->actingAs($this->owner)
            ->put("/app/students/{$student->id}", [
                'first_name' => 'Mail',
                'last_name' => 'Kid',
                'guardian_name' => 'Father Mail',
                'guardian_phone' => '9876500088',
                'guardian_email' => 'kuldeep.s.chauhan07@gmail.com',
                'whatsapp_opt_in' => false,
                'sms_opt_in' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guardian = Guardian::query()->withoutGlobalScope('tenant')->where('name', 'Father Mail')->firstOrFail();
        $this->assertSame('kuldeep.s.chauhan07@gmail.com', $guardian->email);
        $this->assertTrue($guardian->email_opt_in);
    }

    public function test_admission_number_is_generated_and_increments(): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->actingAs($this->owner)
                ->post('/app/students', ['first_name' => $name])
                ->assertRedirect();
        }

        $numbers = Student::query()
            ->withoutGlobalScope('tenant')
            ->orderBy('id')
            ->pluck('admission_no')
            ->all();

        $year = (int) now()->format('n') >= 4 ? now()->year : now()->year - 1;

        $this->assertSame([$year.'-0001', $year.'-0002', $year.'-0003'], $numbers);
    }

    public function test_manual_admission_number_must_be_unique_within_the_coaching(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/students', ['first_name' => 'Manual', 'admission_no' => 'ADM-1'])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post('/app/students', ['first_name' => 'Clash', 'admission_no' => 'ADM-1'])
            ->assertSessionHasErrors('admission_no');

        $this->assertSame(1, Student::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_students_can_be_filtered_by_class_and_searched_by_school(): void
    {
        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Riya', 'class_level' => 'X', 'school_name' => 'St Marys',
        ]);
        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Mohit', 'class_level' => 'XII', 'school_name' => 'DAV Public',
        ]);

        $this->actingAs($this->owner)
            ->get('/app/students?class_level=XII')
            ->assertOk()
            ->assertSee('Mohit')
            ->assertDontSee('Riya');

        $this->actingAs($this->owner)
            ->get('/app/students?search=St+Marys')
            ->assertOk()
            ->assertSee('Riya')
            ->assertDontSee('Mohit');
    }

    public function test_csv_import_and_export_carry_the_new_columns(): void
    {
        $csv = "admission_no,first_name,class_level,school_name,target_exam_year,date_of_birth,gender,phone,guardian_name,guardian_relation,guardian_phone\n"
            .'IMP-1,Neha,XI,City Montessori,2028,2010-02-20,female,9876500021,Sunita,mother,9876500022'."\n";

        $this->actingAs($this->owner)
            ->post('/app/students/import', [
                'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            ])
            ->assertRedirect();

        $student = Student::query()->withoutGlobalScope('tenant')->where('admission_no', 'IMP-1')->firstOrFail();
        $this->assertSame('XI', $student->class_level);
        $this->assertSame('City Montessori', $student->school_name);
        $this->assertSame('2028', $student->target_exam_year);
        $this->assertSame('female', $student->gender);
        $this->assertSame('2010-02-20', $student->date_of_birth->toDateString());
        $this->assertSame('mother', $student->guardians()->first()->relation);

        $export = $this->actingAs($this->owner)->get('/app/students/export.csv');
        $export->assertOk();

        $content = $export->streamedContent();
        $this->assertStringContainsString('class_level', $content);
        $this->assertStringContainsString('City Montessori', $content);
        $this->assertStringContainsString('2028', $content);
    }

    public function test_imported_students_can_be_bulk_added_to_a_batch(): void
    {
        foreach (['Imported One', 'Imported Two'] as $name) {
            $this->actingAs($this->owner)
                ->post('/app/students', ['first_name' => $name])
                ->assertRedirect();
        }

        $this->actingAs($this->owner)
            ->post('/app/academics/batches', ['name' => 'JEE Morning'])
            ->assertRedirect();

        $studentIds = Student::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->owner->tenant_id)
            ->pluck('id')
            ->all();
        $batch = Batch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->owner->tenant_id)
            ->where('name', 'JEE Morning')
            ->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/students/bulk-enrol', [
                'student_ids' => $studentIds,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Enrolment::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->owner->tenant_id)
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->count());

        // Repeating the action must not create duplicate enrolments.
        $this->actingAs($this->owner)
            ->post('/app/students/bulk-enrol', [
                'student_ids' => $studentIds,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Enrolment::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->owner->tenant_id)
            ->where('batch_id', $batch->id)
            ->count());
    }

    public function test_move_mode_replaces_existing_batches(): void
    {
        $this->actingAs($this->owner)->post('/app/students', ['first_name' => 'Mover']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'JEE Morning']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'NEET Evening']);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Mover')->firstOrFail();
        $jee = Batch::query()->withoutGlobalScope('tenant')->where('name', 'JEE Morning')->firstOrFail();
        $neet = Batch::query()->withoutGlobalScope('tenant')->where('name', 'NEET Evening')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $jee->id,
        ]);

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $neet->id,
            'mode' => 'move',
        ])->assertRedirect();

        $active = Enrolment::query()
            ->withoutGlobalScope('tenant')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->all();

        $this->assertSame([$neet->id], $active);
        $this->assertDatabaseHas('enrolments', [
            'student_id' => $student->id,
            'batch_id' => $jee->id,
            'status' => 'transferred',
        ]);
    }

    public function test_single_batch_mode_forces_a_move_even_when_add_is_requested(): void
    {
        $this->actingAs($this->owner)->post('/app/academics/enrolment-rule', ['single_batch_mode' => true])
            ->assertRedirect();

        $this->actingAs($this->owner)->post('/app/students', ['first_name' => 'Solo']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'Class X A']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'Class X B']);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Solo')->firstOrFail();
        $first = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class X A')->firstOrFail();
        $second = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class X B')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $first->id,
        ]);

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $second->id,
            'mode' => 'add',
        ]);

        $active = Enrolment::query()
            ->withoutGlobalScope('tenant')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->all();

        $this->assertSame([$second->id], $active);
    }

    public function test_student_can_be_removed_from_a_single_batch(): void
    {
        $this->actingAs($this->owner)->post('/app/students', ['first_name' => 'Leaver']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'Physics Batch']);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Leaver')->firstOrFail();
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Physics Batch')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $batch->id,
        ]);

        $this->actingAs($this->owner)
            ->delete("/app/students/{$student->id}/batches/{$batch->id}")
            ->assertRedirect();

        $this->assertSame(0, Enrolment::query()
            ->withoutGlobalScope('tenant')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->count());

        $this->assertDatabaseHas('enrolments', [
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => 'left',
        ]);

        // Re-adding then removing again must not break the unique constraint.
        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$student->id],
            'batch_id' => $batch->id,
        ]);

        $this->actingAs($this->owner)
            ->delete("/app/students/{$student->id}/batches/{$batch->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_students_can_be_filtered_to_show_only_unassigned(): void
    {
        $this->actingAs($this->owner)->post('/app/students', ['first_name' => 'Riya']);
        $this->actingAs($this->owner)->post('/app/students', ['first_name' => 'Mohit']);
        $this->actingAs($this->owner)->post('/app/academics/batches', ['name' => 'Foundation']);

        $assigned = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Riya')->firstOrFail();
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Foundation')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students/bulk-enrol', [
            'student_ids' => [$assigned->id],
            'batch_id' => $batch->id,
        ]);

        $this->actingAs($this->owner)
            ->get('/app/students?batch_id=unassigned')
            ->assertOk()
            ->assertSee('Mohit')
            ->assertDontSee('Riya');
    }

    public function test_student_can_be_edited_marked_left_and_deleted_safely(): void
    {
        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Neha',
            'phone' => '9876500001',
            'guardian_name' => 'Father',
            'guardian_phone' => '9876500002',
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Neha')->firstOrFail();

        $this->actingAs($this->owner)
            ->put('/app/students/'.$student->id, [
                'first_name' => 'Neha',
                'last_name' => 'Sharma',
                'phone' => '9876500001',
                'guardian_name' => 'Father',
                'guardian_phone' => '9876500002',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Sharma', $student->fresh()->last_name);

        $this->actingAs($this->owner)
            ->post('/app/students/'.$student->id.'/status', ['status' => 'left'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('left', $student->fresh()->status);

        $this->actingAs($this->owner)
            ->post('/app/students/'.$student->id.'/status', ['status' => 'active'])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->delete('/app/students/'.$student->id)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }
}
