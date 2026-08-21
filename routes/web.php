<?php

use App\Http\Controllers\Api\V1\MobileApiController;
use App\Http\Controllers\App\AlertController;
use App\Http\Controllers\App\AcademicsController;
use App\Http\Controllers\App\AnnouncementController;
use App\Http\Controllers\App\AttendanceController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\EnquiryController;
use App\Http\Controllers\App\FeeController;
use App\Http\Controllers\App\NoteController;
use App\Http\Controllers\App\ParentPortalController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\StaffController;
use App\Http\Controllers\App\StudentController;
use App\Http\Controllers\App\StudentImportExportController;
use App\Http\Controllers\Marketing\LandingController;
use App\Http\Controllers\Platform\CoachingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'platform'])->name('marketing.platform');
Route::get('/c/{slug}', [LandingController::class, 'coaching'])->name('marketing.coaching');
Route::post('/c/{slug}/enquiry', [LandingController::class, 'enquiry'])
    ->middleware('throttle:10,1')
    ->name('marketing.enquiry');

Route::post('/webhooks/razorpay/{tenant}', RazorpayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.razorpay');

Route::middleware(['auth', 'verified', 'platform'])->prefix('platform')->group(function () {
    Route::get('/coachings', [CoachingController::class, 'index'])->name('platform.coachings.index');
    Route::post('/coachings', [CoachingController::class, 'store'])->name('platform.coachings.store');
    Route::patch('/coachings/{coaching}/status', [CoachingController::class, 'updateStatus'])->name('platform.coachings.status');
    Route::post('/coachings/{coaching}/reset-owner-password', [CoachingController::class, 'resetOwnerPassword'])
        ->middleware('throttle:10,1')
        ->name('platform.coachings.reset-password');
});

Route::middleware(['auth', 'verified', 'tenant'])->prefix('app')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('/students/bulk-enrol', [StudentController::class, 'bulkEnrol'])
        ->name('students.bulk-enrol');
    Route::delete('/students/{student}/batches/{batch}', [StudentController::class, 'unenrol'])
        ->name('students.unenrol');
    Route::get('/students/export.csv', [StudentImportExportController::class, 'export'])
        ->middleware('throttle:20,1')
        ->name('students.export');
    Route::get('/students/import-template.csv', [StudentImportExportController::class, 'template'])
        ->middleware('throttle:20,1')
        ->name('students.import-template');
    Route::post('/students/import', [StudentImportExportController::class, 'import'])
        ->middleware('throttle:10,1')
        ->name('students.import');

    Route::get('/academics', [AcademicsController::class, 'index'])->name('academics.index');
    Route::post('/academics/branches', [AcademicsController::class, 'storeBranch'])->name('academics.branches.store');
    Route::put('/academics/branches/{branch}', [AcademicsController::class, 'updateBranch'])->name('academics.branches.update');
    Route::delete('/academics/branches/{branch}', [AcademicsController::class, 'destroyBranch'])->name('academics.branches.destroy');
    Route::post('/academics/categories', [AcademicsController::class, 'storeCategory'])->name('academics.categories.store');
    Route::post('/academics/courses', [AcademicsController::class, 'storeCourse'])->name('academics.courses.store');
    Route::put('/academics/courses/{course}', [AcademicsController::class, 'updateCourse'])->name('academics.courses.update');
    Route::delete('/academics/courses/{course}', [AcademicsController::class, 'destroyCourse'])->name('academics.courses.destroy');
    Route::post('/academics/batches', [AcademicsController::class, 'storeBatch'])->name('academics.batches.store');
    Route::put('/academics/batches/{batch}', [AcademicsController::class, 'updateBatch'])->name('academics.batches.update');
    Route::delete('/academics/batches/{batch}', [AcademicsController::class, 'destroyBatch'])->name('academics.batches.destroy');
    Route::post('/academics/subjects', [AcademicsController::class, 'storeSubject'])->name('academics.subjects.store');
    Route::put('/academics/subjects/{subject}', [AcademicsController::class, 'updateSubject'])->name('academics.subjects.update');
    Route::delete('/academics/subjects/{subject}', [AcademicsController::class, 'destroySubject'])->name('academics.subjects.destroy');
    Route::post('/academics/enrolment-rule', [AcademicsController::class, 'updateEnrolmentRule'])
        ->name('academics.enrolment-rule');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/sessions', [AttendanceController::class, 'storeSession'])->name('attendance.sessions.store');
    Route::get('/attendance/sessions/{session}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/sessions/{session}/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');

    Route::get('/fees', [FeeController::class, 'index'])->name('fees.index');
    Route::get('/fees/receipts/{receipt}', [FeeController::class, 'showReceipt'])->name('fees.receipts.show');
    Route::post('/fees/invoices', [FeeController::class, 'storeInvoice'])->name('fees.invoices.store');
    Route::post('/fees/payments', [FeeController::class, 'storePayment'])->name('fees.payments.store');
    Route::post('/fees/batch-dues', [FeeController::class, 'generateBatchDues'])->name('fees.batch-dues.generate');
    Route::post('/fees/razorpay/order', [FeeController::class, 'createRazorpayOrder'])->name('fees.razorpay.order');
    Route::post('/fees/gateway', [SettingsController::class, 'saveGateway'])->name('fees.gateway.save');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');

    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
    Route::post('/enquiries/{enquiry}/follow-up', [EnquiryController::class, 'followUp'])->name('enquiries.followup');
    Route::post('/enquiries/{enquiry}/convert', [EnquiryController::class, 'convert'])->name('enquiries.convert');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::post('/staff/assignments', [StaffController::class, 'assign'])->name('staff.assign');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/defaulters.csv', [ReportController::class, 'exportDefaulters'])->name('reports.defaulters');
    Route::get('/reports/pending-dues.csv', [ReportController::class, 'exportPendingDues'])->name('reports.pending');
    Route::get('/reports/invoices.csv', [ReportController::class, 'exportInvoices'])->name('reports.invoices');
    Route::get('/reports/receipts.csv', [ReportController::class, 'exportReceipts'])->name('reports.receipts');

    Route::get('/parent', [ParentPortalController::class, 'index'])->name('parent.index');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/dispatch', [AlertController::class, 'dispatchPending'])->name('alerts.dispatch');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::match(['put', 'post'], '/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/gateway', [SettingsController::class, 'saveGateway'])->name('settings.gateway.save');
    Route::post('/settings/alerts', [SettingsController::class, 'saveAlerts'])->name('settings.alerts.save');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
