<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Receipt;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
            'tenant_id' => $request->user()->tenant_id,
            'min_app_version' => config('coaching.min_app_version', '1.0.0'),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['enrolments.batch', 'guardians'])
            ->where('status', 'active')
            ->latest()
            ->paginate(50);

        return response()->json($students);
    }

    public function attendance(Request $request): JsonResponse
    {
        return response()->json(
            AttendanceRecord::query()
                ->with(['student', 'classSession.batch'])
                ->latest('marked_at')
                ->paginate(50)
        );
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json(
            Invoice::query()->with('student')->latest()->paginate(50)
        );
    }

    public function receipts(Request $request): JsonResponse
    {
        return response()->json(
            Receipt::query()->with('student')->latest()->paginate(50)
        );
    }

    public function announcements(): JsonResponse
    {
        return response()->json(
            Announcement::query()->where('status', 'published')->latest('published_at')->paginate(50)
        );
    }

    public function notes(): JsonResponse
    {
        return response()->json(
            Note::query()->where('is_published', true)->latest('published_at')->paginate(50)
        );
    }
}
