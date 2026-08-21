<?php

namespace App\Http\Controllers\App;

use App\Support\Format\IndiaDate;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportExportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'admission_no', 'first_name', 'last_name', 'class_level', 'school_name', 'target_exam_year',
                'date_of_birth', 'gender', 'phone', 'email', 'address', 'source', 'batch',
                'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_alternate_phone', 'status',
            ]);
            fputcsv($out, [
                'ADM-001', 'Aarav', 'Sharma', 'XII', 'Kendriya Vidyalaya', '2027',
                '14-05-2009', 'male', '9876500011', 'aarav@example.com', 'Civil Lines, Kannauj', 'Referral', '',
                'Rakesh Sharma', 'father', '9876500012', '9876500013', 'active',
            ]);
            fputcsv($out, [
                'ADM-002', 'Riya', 'Verma', 'X', 'St Marys School', '',
                '22-08-2011', 'female', '9876500021', '', 'Near Bus Stand', 'Pamphlet', '',
                'Sunita Verma', 'mother', '9876500022', '', 'active',
            ]);
            fputcsv($out, [
                'ADM-003', 'Mohit', 'Yadav', 'Dropper', 'Private Tutor', '2027',
                '10-01-2007', 'male', '9876500031', 'mohit@example.com', 'Village Road', 'Walk-in', '',
                'Suresh Yadav', 'father', '9876500032', '', 'active',
            ]);
            fclose($out);
        }, 'students-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'admission_no', 'first_name', 'last_name', 'class_level', 'school_name', 'target_exam_year',
                'date_of_birth', 'gender', 'phone', 'email', 'address', 'source', 'batch',
                'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_alternate_phone', 'status',
            ]);

            Student::query()
                ->with(['enrolments.batch', 'guardians'])
                ->chunk(200, function ($students) use ($out) {
                    foreach ($students as $student) {
                        fputcsv($out, [
                            $student->admission_no,
                            $student->first_name,
                            $student->last_name,
                            $student->class_level,
                            $student->school_name,
                            $student->target_exam_year,
                            IndiaDate::format($student->date_of_birth),
                            $student->gender,
                            $student->phone,
                            $student->email,
                            $student->address,
                            $student->source,
                            $student->enrolments->first()?->batch?->name,
                            $student->guardians->first()?->name,
                            $student->guardians->first()?->relation,
                            $student->guardians->first()?->phone,
                            $student->guardians->first()?->alternate_phone,
                            $student->status,
                        ]);
                    }
                });

            fclose($out);
        }, 'students-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (! $header) {
            return back()->with('error', 'CSV appears to be empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $tenantId = (int) $request->user()->tenant_id;
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $header, $tenantId, $request, &$created, &$skipped) {
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

                $admissionNo = trim((string) ($data['admission_no'] ?? ''));
                if ($admissionNo === '') {
                    $skipped++;

                    continue;
                }

                $duplicate = Student::query()->where('admission_no', $admissionNo)->exists()
                    || (! empty($data['phone']) && Student::query()->where('phone', trim((string) $data['phone']))->exists());

                if ($duplicate) {
                    $skipped++;

                    continue;
                }

                $dob = trim((string) ($data['date_of_birth'] ?? ''));

                $student = Student::query()->create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $request->user()->branch_id,
                    'admission_no' => $admissionNo,
                    'first_name' => trim((string) ($data['first_name'] ?? 'Unknown')),
                    'last_name' => trim((string) ($data['last_name'] ?? '')) ?: null,
                    'class_level' => trim((string) ($data['class_level'] ?? '')) ?: null,
                    'school_name' => trim((string) ($data['school_name'] ?? '')) ?: null,
                    'target_exam_year' => trim((string) ($data['target_exam_year'] ?? '')) ?: null,
                    'date_of_birth' => IndiaDate::toStorage($dob),
                    'gender' => in_array(strtolower(trim((string) ($data['gender'] ?? ''))), ['male', 'female', 'other'], true)
                        ? strtolower(trim((string) $data['gender']))
                        : null,
                    'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                    'email' => trim((string) ($data['email'] ?? '')) ?: null,
                    'address' => trim((string) ($data['address'] ?? '')) ?: null,
                    'source' => trim((string) ($data['source'] ?? '')) ?: null,
                    'status' => 'active',
                    'joined_on' => now()->toDateString(),
                ]);

                if (! empty($data['guardian_name']) && ! empty($data['guardian_phone'])) {
                    $relation = strtolower(trim((string) ($data['guardian_relation'] ?? '')));

                    $guardian = Guardian::query()->create([
                        'tenant_id' => $tenantId,
                        'name' => trim((string) $data['guardian_name']),
                        'phone' => trim((string) $data['guardian_phone']),
                        'alternate_phone' => trim((string) ($data['guardian_alternate_phone'] ?? '')) ?: null,
                        'relation' => in_array($relation, ['father', 'mother', 'guardian'], true) ? $relation : 'guardian',
                        'sms_opt_in' => true,
                        'whatsapp_opt_in' => false,
                    ]);
                    $student->guardians()->attach($guardian->id, ['is_primary' => true]);
                }

                if (! empty($data['batch'])) {
                    $batch = Batch::query()->where('name', trim((string) $data['batch']))->first();
                    if ($batch) {
                        Enrolment::query()->create([
                            'tenant_id' => $tenantId,
                            'student_id' => $student->id,
                            'batch_id' => $batch->id,
                            'enrolled_on' => now()->toDateString(),
                            'status' => 'active',
                        ]);
                    }
                }

                $created++;
            }
        });

        fclose($handle);

        return back()->with('success', "Imported {$created} student(s); skipped {$skipped} duplicate/invalid row(s).");
    }
}
