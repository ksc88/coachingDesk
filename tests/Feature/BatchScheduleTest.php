<?php

namespace Tests\Feature;

use App\Domain\Academics\BatchScheduleFormatter;
use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->owner = app(TenantProvisioner::class)->provision([
            'name' => 'Schedule Coaching',
            'code' => 'SCH',
            'slug' => 'schedule-coaching',
            'owner_name' => 'Sch Owner',
            'owner_email' => 'owner@sch.test',
            'password' => 'secret-pass-123',
        ])['owner'];
    }

    public function test_formatter_collapses_consecutive_weekdays(): void
    {
        $formatter = new BatchScheduleFormatter;

        $this->assertSame(
            'Mon–Sat · 07:00–09:00 · Morning',
            $formatter->format([1, 2, 3, 4, 5, 6], '07:00', '09:00', 'morning'),
        );

        $this->assertSame(
            'Mon/Wed/Fri · 17:00–19:00 · Evening',
            $formatter->format([1, 3, 5], '17:00', '19:00', 'evening'),
        );
    }

    public function test_creating_a_batch_stores_structured_schedule(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/academics/subjects', ['name' => 'Physics'])
            ->assertRedirect();

        $physics = \App\Models\Subject::query()->withoutGlobalScope('tenant')->where('name', 'Physics')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/academics/batches', [
                'name' => 'JEE Morning',
                'weekdays' => [1, 2, 3, 4, 5, 6],
                'starts_at' => '07:00',
                'ends_at' => '09:00',
                'shift' => 'morning',
                'default_fee' => 5000,
                'subject_ids' => [$physics->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'JEE Morning')->firstOrFail();

        $this->assertSame([1, 2, 3, 4, 5, 6], $batch->weekdays);
        $this->assertStringStartsWith('07:00', (string) $batch->starts_at);
        $this->assertStringStartsWith('09:00', (string) $batch->ends_at);
        $this->assertSame('morning', $batch->shift);
        $this->assertSame('Mon–Sat · 07:00–09:00 · Morning', $batch->timing);
        $this->assertTrue($batch->subjects()->where('subjects.id', $physics->id)->exists());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/academics/batches', [
                'name' => 'Bad Timing',
                'weekdays' => [1],
                'starts_at' => '09:00',
                'ends_at' => '07:00',
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_cannot_delete_batch_with_active_students(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/academics/batches', ['name' => 'Busy Batch', 'weekdays' => [1], 'starts_at' => '16:00', 'ends_at' => '17:00'])
            ->assertRedirect();

        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Busy Batch')->firstOrFail();

        \App\Models\Student::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'admission_no' => 'SCH-1',
            'first_name' => 'Test',
            'status' => 'active',
            'joined_on' => now()->toDateString(),
        ]);
        $student = \App\Models\Student::query()->withoutGlobalScope('tenant')->where('admission_no', 'SCH-1')->firstOrFail();
        \App\Models\Enrolment::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)
            ->delete('/app/academics/batches/'.$batch->id)
            ->assertRedirect()
            ->assertSessionHasErrors('batch');

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'is_active' => 1]);
    }

    public function test_empty_batch_can_be_deleted_and_course_blocked_when_used(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/academics/courses', ['name' => 'English'])
            ->assertRedirect();

        $course = \App\Models\Course::query()->withoutGlobalScope('tenant')->where('name', 'English')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/academics/batches', [
                'name' => 'Empty Batch',
                'course_id' => $course->id,
                'weekdays' => [2],
                'starts_at' => '16:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect();

        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Empty Batch')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete('/app/academics/courses/'.$course->id)
            ->assertSessionHasErrors('course');

        $this->actingAs($this->owner)
            ->delete('/app/academics/batches/'.$batch->id)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);

        $this->actingAs($this->owner)
            ->delete('/app/academics/courses/'.$course->id)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_batch_can_be_updated(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/academics/batches', [
                'name' => 'Old Name',
                'weekdays' => [1, 3],
                'starts_at' => '16:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect();

        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Old Name')->firstOrFail();

        $this->actingAs($this->owner)
            ->put('/app/academics/batches/'.$batch->id, [
                'name' => 'New Name',
                'weekdays' => [2, 4],
                'starts_at' => '17:00',
                'ends_at' => '18:00',
                'default_fee' => 1500,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('New Name', $batch->name);
        $this->assertSame([2, 4], $batch->weekdays);
        $this->assertSame('1500.00', (string) $batch->default_fee);
    }
}
