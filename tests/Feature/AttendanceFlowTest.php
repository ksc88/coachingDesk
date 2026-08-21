<?php

namespace Tests\Feature;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Guardian;
use App\Models\NotificationOutbox;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Tenant $tenant;

    protected Batch $batch;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $result = app(TenantProvisioner::class)->provision([
            'name' => 'Alert Coaching',
            'code' => 'ALRT',
            'slug' => 'alert-coaching',
            'owner_name' => 'Alert Owner',
            'owner_email' => 'owner@alert.test',
            'password' => 'secret-pass-123',
        ]);

        $this->tenant = $result['tenant'];
        $this->owner = $result['owner'];
        TenantContext::set($this->tenant);

        $this->actingAs($this->owner)->post('/app/academics/batches', [
            'name' => 'Class X English',
            'weekdays' => [1, 3, 5],
            'starts_at' => '16:00',
            'ends_at' => '17:00',
            'default_fee' => 1200,
        ]);

        $this->batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class X English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/academics/subjects', [
            'name' => 'English',
        ]);

        $this->subject = Subject::query()->withoutGlobalScope('tenant')->where('name', 'English')->firstOrFail();
    }

    public function test_mark_save_correct_and_finalize_locks_the_sheet(): void
    {
        $present = $this->admit('Present', 'Kid', whatsapp: true);
        $absent = $this->admit('Absent', 'Kid', whatsapp: true);
        $late = $this->admit('Late', 'Kid', whatsapp: true);
        $leave = $this->admit('Leave', 'Kid', email: true);

        $this->actingAs($this->owner)
            ->post('/app/attendance/sessions', [
                'batch_id' => $this->batch->id,
                'subject_id' => $this->subject->id,
                'session_date' => now()->toDateString(),
                'topic' => 'Grammar',
            ])
            ->assertRedirect();

        $session = ClassSession::query()->withoutGlobalScope('tenant')->firstOrFail();

        $this->actingAs($this->owner)
            ->post("/app/attendance/sessions/{$session->id}/mark", [
                'marks' => [
                    ['student_id' => $present->id, 'status' => 'present'],
                    ['student_id' => $absent->id, 'status' => 'absent'],
                    ['student_id' => $late->id, 'status' => 'late'],
                    ['student_id' => $leave->id, 'status' => 'leave'],
                ],
                'finalize' => false,
                'notify_absent' => true,
                'notify_present' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('present', $this->recordStatus($session, $present));
        $this->assertSame('absent', $this->recordStatus($session, $absent));
        $this->assertSame('late', $this->recordStatus($session, $late));
        $this->assertSame('leave', $this->recordStatus($session, $leave));
        $this->assertSame('scheduled', $session->fresh()->status);

        $this->actingAs($this->owner)
            ->post("/app/attendance/sessions/{$session->id}/mark", [
                'marks' => [
                    ['student_id' => $late->id, 'status' => 'present'],
                    ['student_id' => $present->id, 'status' => 'present'],
                    ['student_id' => $absent->id, 'status' => 'absent'],
                    ['student_id' => $leave->id, 'status' => 'leave'],
                ],
                'finalize' => true,
                'notify_absent' => true,
                'notify_present' => false,
            ])
            ->assertRedirect();

        $this->assertSame('present', $this->recordStatus($session, $late));
        $this->assertSame('completed', $session->fresh()->status);

        $this->actingAs($this->owner)
            ->post("/app/attendance/sessions/{$session->id}/mark", [
                'marks' => [
                    ['student_id' => $absent->id, 'status' => 'present'],
                ],
                'finalize' => false,
                'notify_absent' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('absent', $this->recordStatus($session, $absent));
    }

    public function test_absence_queues_whatsapp_then_email_and_skips_no_consent(): void
    {
        $wa = $this->admit('Wa', 'Student', whatsapp: true);
        $mail = $this->admit('Mail', 'Student', email: true);
        $none = $this->admit('None', 'Student');

        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $wa->id, 'status' => 'absent'],
            ['student_id' => $mail->id, 'status' => 'absent'],
            ['student_id' => $none->id, 'status' => 'absent'],
        ], false, true, false);

        $rows = NotificationOutbox::query()->withoutGlobalScope('tenant')->orderBy('id')->get();
        $this->assertCount(2, $rows);

        $waRow = $rows->firstWhere('student_id', $wa->id);
        $this->assertSame('whatsapp', $waRow->channel);
        $this->assertSame('attendance.absent', $waRow->event_type);
        $this->assertSame('pending', $waRow->status);
        $this->assertStringContainsString('ABSENT', $waRow->body);
        $this->assertStringContainsString('Class X English', $waRow->body);
        $this->assertSame($session->id, $waRow->payload['class_session_id']);

        $mailRow = $rows->firstWhere('student_id', $mail->id);
        $this->assertSame('email', $mailRow->channel);
        $this->assertSame('parent-mail@alert.test', $mailRow->recipient_email);

        $this->assertNull($rows->firstWhere('student_id', $none->id));
    }

    public function test_absent_email_includes_topic_when_set(): void
    {
        $student = $this->admit('Topic', 'Kid', email: true);
        $session = $this->createSession();
        $session->update(['topic' => 'Chapter 3 grammar']);

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $row = NotificationOutbox::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('email', $row->channel);
        $this->assertStringContainsString('Chapter 3 grammar', $row->body);
    }

    public function test_creating_duplicate_open_sheet_reuses_existing(): void
    {
        $this->actingAs($this->owner)->post('/app/attendance/sessions', [
            'batch_id' => $this->batch->id,
            'subject_id' => $this->subject->id,
            'session_date' => now()->toDateString(),
            'topic' => 'First',
        ])->assertRedirect();

        $first = ClassSession::query()->withoutGlobalScope('tenant')->latest('id')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/attendance/sessions', [
            'batch_id' => $this->batch->id,
            'subject_id' => $this->subject->id,
            'session_date' => now()->toDateString(),
            'topic' => 'Second',
        ])->assertRedirect(route('attendance.show', $first, absolute: false));

        $this->assertSame(1, ClassSession::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_present_does_not_alert_unless_requested_and_absent_is_not_duplicated(): void
    {
        $student = $this->admit('Dup', 'Student', whatsapp: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'present'],
        ], false, true, false);

        $this->assertSame(0, NotificationOutbox::query()->withoutGlobalScope('tenant')->count());

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $this->assertSame(1, NotificationOutbox::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_safe_mode_dispatch_logs_instead_of_live_send(): void
    {
        $student = $this->admit('Safe', 'Student', whatsapp: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        $row = NotificationOutbox::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('sent', $row->status);
        $this->assertSame('safe', $row->payload['delivery_mode'] ?? null);
        $this->assertNotEmpty($row->provider_message_id);
        $this->assertStringStartsWith('whatsapp_', $row->provider_message_id);
    }

    public function test_alert_settings_and_queue_page(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/alerts', [
                'mode' => 'safe',
                'whatsapp_provider' => 'meta',
                'whatsapp_from' => '15550001111',
                'email_from' => 'desk@alert.test',
                'sms_provider' => 'brevo',
                'sms_sender' => 'MYCOACH',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->tenant->refresh();
        $this->assertSame('safe', $this->tenant->settings['alerts']['mode']);
        $this->assertSame('brevo', $this->tenant->settings['alerts']['sms_provider']);
        $this->assertSame('MYCOACH', $this->tenant->settings['alerts']['sms_sender']);
        $this->assertFalse($this->tenant->alertsAreLive());

        $this->actingAs($this->owner)
            ->get('/app/alerts')
            ->assertOk();
    }

    public function test_live_waapi_sends_via_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'waapi.app/*' => \Illuminate\Support\Facades\Http::response([
                'status' => 'success',
                'data' => ['id' => 'waapi-msg-1'],
            ], 200),
        ]);

        $this->tenant->settings = array_merge($this->tenant->settings ?? [], [
            'alerts' => [
                'mode' => 'live',
                'whatsapp_provider' => 'waapi',
                'whatsapp_from' => '102486',
                'whatsapp_token' => 'test-token-not-real',
            ],
        ]);
        $this->tenant->save();

        $student = $this->admit('Waapi', 'Parent', whatsapp: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        $row = NotificationOutbox::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('sent', $row->status);
        $this->assertSame('live', $row->payload['delivery_mode'] ?? null);
        $this->assertSame('waapi', $row->payload['provider'] ?? null);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), '/instances/102486/client/action/send-message')
                && $request->hasHeader('Authorization', 'Bearer test-token-not-real')
                && str_ends_with((string) $request['chatId'], '@c.us');
        });
    }

    public function test_absence_and_present_queue_sms_when_whatsapp_is_off(): void
    {
        $absent = $this->admit('SmsAbs', 'Student', sms: true);
        $present = $this->admit('SmsPre', 'Student', sms: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $absent->id, 'status' => 'absent'],
            ['student_id' => $present->id, 'status' => 'present'],
        ], false, true, true);

        $rows = NotificationOutbox::query()->withoutGlobalScope('tenant')->get();
        $this->assertCount(2, $rows);

        $absentRow = $rows->firstWhere('student_id', $absent->id);
        $this->assertSame('sms', $absentRow->channel);
        $this->assertSame('attendance.absent', $absentRow->event_type);
        $this->assertStringContainsString('ABSENT', $absentRow->body);

        $presentRow = $rows->firstWhere('student_id', $present->id);
        $this->assertSame('sms', $presentRow->channel);
        $this->assertSame('attendance.present', $presentRow->event_type);
        $this->assertStringContainsString('PRESENT', $presentRow->body);
    }

    public function test_live_brevo_sends_sms_via_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.brevo.com/v3/transactionalSMS/send' => \Illuminate\Support\Facades\Http::response([
                'messageId' => 1511882900100020,
            ], 201),
        ]);

        $this->tenant->settings = array_merge($this->tenant->settings ?? [], [
            'alerts' => [
                'mode' => 'live',
                'sms_provider' => 'brevo',
                'sms_sender' => 'MyShop',
                'sms_api_key' => 'test-brevo-key',
            ],
        ]);
        $this->tenant->save();

        $student = $this->admit('Brevo', 'Parent', sms: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        $row = NotificationOutbox::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('sent', $row->status);
        $this->assertSame('live', $row->payload['delivery_mode'] ?? null);
        $this->assertSame('brevo', $row->payload['provider'] ?? null);
        $this->assertSame('1511882900100020', (string) $row->provider_message_id);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/transactionalSMS/send'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && $request['sender'] === 'MyShop'
                && $request['type'] === 'transactional'
                && $request['tag'] === 'attendance.absent'
                && str_starts_with((string) $request['recipient'], '91')
                && str_contains((string) $request['content'], 'ABSENT');
        });
    }

    public function test_live_brevo_sends_email_via_http(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.brevo.com/v3/smtp/email' => \Illuminate\Support\Facades\Http::response([
                'messageId' => 'email-msg-99',
            ], 201),
        ]);

        $this->tenant->settings = array_merge($this->tenant->settings ?? [], [
            'alerts' => [
                'mode' => 'live',
                'email_provider' => 'brevo',
                'email_from' => 'desk@alert.test',
                'email_from_name' => 'Alert Coaching',
                'sms_api_key' => 'test-brevo-key',
            ],
        ]);
        $this->tenant->save();

        $student = $this->admit('MailLive', 'Parent', email: true);
        $session = $this->createSession();

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], false, true, false);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        $row = NotificationOutbox::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('email', $row->channel);
        $this->assertSame('sent', $row->status);
        $this->assertSame('live', $row->payload['delivery_mode'] ?? null);
        $this->assertSame('brevo', $row->payload['provider'] ?? null);
        $this->assertSame('email-msg-99', (string) $row->provider_message_id);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && ($request['sender']['email'] ?? null) === 'desk@alert.test'
                && ($request['to'][0]['email'] ?? null) === 'parent-mail@alert.test'
                && str_contains((string) $request['subject'], 'Absent')
                && str_contains((string) $request['textContent'], 'ABSENT');
        });
    }

    protected function admit(string $first, string $last, bool $whatsapp = false, bool $email = false, bool $sms = false): Student
    {
        $payload = [
            'first_name' => $first,
            'last_name' => $last,
            'batch_id' => $this->batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1200,
            'raise_first_invoice' => false,
        ];

        if ($whatsapp || $email || $sms) {
            $payload['guardian_name'] = $first.' Parent';
            $payload['guardian_phone'] = '98'.substr(str_pad((string) abs(crc32($first.$last)), 8, '0'), 0, 8);
            $payload['guardian_email'] = $email ? 'parent-mail@alert.test' : null;
            $payload['whatsapp_opt_in'] = $whatsapp;
            $payload['sms_opt_in'] = $sms;
        }

        $this->actingAs($this->owner)->post('/app/students', $payload);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', $first)->where('last_name', $last)->firstOrFail();

        $guardian = Guardian::query()->withoutGlobalScope('tenant')->where('name', $first.' Parent')->first();
        if ($guardian) {
            $guardian->update([
                'whatsapp_opt_in' => $whatsapp,
                'sms_opt_in' => $sms,
                'email_opt_in' => $email,
                'email' => $email ? 'parent-mail@alert.test' : null,
            ]);
        }

        return $student;
    }

    protected function createSession(): ClassSession
    {
        $this->actingAs($this->owner)->post('/app/attendance/sessions', [
            'batch_id' => $this->batch->id,
            'subject_id' => $this->subject->id,
            'session_date' => now()->toDateString(),
            'topic' => 'Test class',
        ]);

        return ClassSession::query()->withoutGlobalScope('tenant')->latest('id')->firstOrFail();
    }

    protected function recordStatus(ClassSession $session, Student $student): string
    {
        return $session->attendanceRecords()->where('student_id', $student->id)->value('status');
    }
}
