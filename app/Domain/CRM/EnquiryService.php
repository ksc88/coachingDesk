<?php

namespace App\Domain\CRM;

use App\Domain\Identity\AuditLogger;
use App\Models\Enquiry;
use App\Models\EnquiryFollowUp;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnquiryService
{
    public function __construct(protected AuditLogger $audit) {}

    public function addFollowUp(Enquiry $enquiry, array $data): EnquiryFollowUp
    {
        $notes = trim((string) ($data['notes'] ?? ''));

        $followUp = EnquiryFollowUp::query()->create([
            'tenant_id' => $enquiry->tenant_id,
            'enquiry_id' => $enquiry->id,
            'user_id' => Auth::id(),
            'type' => $data['type'] ?? 'call',
            'notes' => $notes !== '' ? $notes : 'Status updated to '.($data['status'] ?? $enquiry->status),
            'outcome' => $data['outcome'] ?? null,
            'followed_up_at' => $data['followed_up_at'] ?? now(),
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
        ]);

        $enquiry->update([
            'status' => $data['status'] ?? ($enquiry->status === 'new' ? 'contacted' : $enquiry->status),
            'next_follow_up_at' => $data['next_follow_up_at'] ?? $enquiry->next_follow_up_at,
            'notes' => $data['enquiry_notes'] ?? $enquiry->notes,
        ]);

        return $followUp;
    }

    public function convertToAdmission(Enquiry $enquiry, array $data): Student
    {
        return DB::transaction(function () use ($enquiry, $data) {
            $admissionNo = $data['admission_no'] ?? ('ADM-'.now()->format('ymd').'-'.random_int(100, 999));

            $student = Student::query()->create([
                'tenant_id' => $enquiry->tenant_id,
                'branch_id' => $enquiry->branch_id,
                'admission_no' => $admissionNo,
                'first_name' => $data['first_name'] ?? $enquiry->name,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $enquiry->phone,
                'email' => $enquiry->email,
                'source' => $data['source'] ?? $enquiry->source,
                'remarks' => $enquiry->notes,
                'status' => 'active',
                'joined_on' => now()->toDateString(),
            ]);

            if (! empty($data['guardian_name'])) {
                $guardian = Guardian::query()->create([
                    'tenant_id' => $enquiry->tenant_id,
                    'name' => $data['guardian_name'],
                    'relation' => $data['guardian_relation'] ?? 'parent',
                    'phone' => $data['guardian_phone'] ?? $enquiry->phone,
                    'email' => $data['guardian_email'] ?? $enquiry->email,
                    'whatsapp_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? $enquiry->whatsapp_opt_in),
                    'sms_opt_in' => (bool) ($data['sms_opt_in'] ?? $enquiry->sms_opt_in ?? true),
                    'consent_at' => now(),
                ]);
                $student->guardians()->attach($guardian->id, ['is_primary' => true]);
            } elseif ($enquiry->whatsapp_opt_in || $enquiry->sms_opt_in) {
                // No separate guardian named — store consent against a guardian using the enquiry contact.
                $guardian = Guardian::query()->create([
                    'tenant_id' => $enquiry->tenant_id,
                    'name' => $enquiry->name,
                    'relation' => 'self',
                    'phone' => $enquiry->phone,
                    'email' => $enquiry->email,
                    'whatsapp_opt_in' => (bool) $enquiry->whatsapp_opt_in,
                    'sms_opt_in' => (bool) $enquiry->sms_opt_in,
                    'consent_at' => now(),
                ]);
                $student->guardians()->attach($guardian->id, ['is_primary' => true]);
            }

            if (! empty($data['batch_id']) || $enquiry->batch_id) {
                Enrolment::query()->create([
                    'tenant_id' => $enquiry->tenant_id,
                    'student_id' => $student->id,
                    'batch_id' => $data['batch_id'] ?? $enquiry->batch_id,
                    'enrolled_on' => now()->toDateString(),
                    'status' => 'active',
                ]);
            }

            $enquiry->update([
                'status' => 'admitted',
                'converted_student_id' => $student->id,
            ]);

            $this->audit->log('enquiry.converted', $enquiry, null, ['student_id' => $student->id]);

            return $student;
        });
    }
}
