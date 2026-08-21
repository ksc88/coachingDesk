<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNotificationOutbox;
use App\Models\NotificationOutbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $alerts = $tenant->settings['alerts'] ?? [];

        $rows = NotificationOutbox::query()
            ->with(['student:id,first_name,last_name,admission_no'])
            ->latest('id')
            ->paginate(30)
            ->through(function (NotificationOutbox $row) {
                $payload = $row->payload ?? [];

                return [
                    'id' => $row->id,
                    'channel' => $row->channel,
                    'event_type' => $row->event_type,
                    'recipient_name' => $row->recipient_name,
                    'recipient_phone' => $row->recipient_phone,
                    'recipient_email' => $row->recipient_email,
                    'body' => $row->body,
                    'status' => $row->status,
                    'attempts' => $row->attempts,
                    'failure_reason' => $row->failure_reason,
                    'provider_message_id' => $row->provider_message_id,
                    'delivery_mode' => $payload['delivery_mode'] ?? 'safe',
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                    'sent_at' => optional($row->sent_at)->toDateTimeString(),
                    'student' => $row->student ? [
                        'admission_no' => $row->student->admission_no,
                        'name' => trim($row->student->first_name.' '.($row->student->last_name ?? '')),
                    ] : null,
                ];
            });

        $counts = [
            'pending' => NotificationOutbox::query()->where('status', 'pending')->count(),
            'sent' => NotificationOutbox::query()->where('status', 'sent')->count(),
            'failed' => NotificationOutbox::query()->where('status', 'failed')->count(),
        ];

        return Inertia::render('Alerts/Index', [
            'rows' => $rows,
            'counts' => $counts,
            'mode' => $alerts['mode'] ?? 'safe',
        ]);
    }

    public function dispatchPending(): RedirectResponse
    {
        $ids = NotificationOutbox::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(200)
            ->pluck('id');

        $sent = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                ProcessNotificationOutbox::dispatchSync($id);
                $row = NotificationOutbox::query()->find($id);
                if (($row?->status) === 'sent') {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($ids->isEmpty()) {
            return back()->with('success', 'No pending alerts to send.');
        }

        return back()->with('success', "Processed {$ids->count()} alert(s): {$sent} sent, {$failed} still pending or failed. Refresh this page for details.");
    }
}
