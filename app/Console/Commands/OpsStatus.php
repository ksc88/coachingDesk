<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use App\Models\PaymentWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OpsStatus extends Command
{
    protected $signature = 'ops:status';

    protected $description = 'Show queue, notification, and webhook health for the pilot';

    public function handle(): int
    {
        $outbox = NotificationOutbox::query()
            ->withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $webhooks = PaymentWebhookEvent::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->components->twoColumnDetail('<fg=cyan>Notification outbox</>', '');
        foreach ($outbox as $status => $total) {
            $this->components->twoColumnDetail("  {$status}", (string) $total);
        }

        $this->components->twoColumnDetail('<fg=cyan>Payment webhooks</>', '');
        foreach ($webhooks as $status => $total) {
            $this->components->twoColumnDetail("  {$status}", (string) $total);
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $pendingJobs = DB::table('jobs')->count();

        $this->components->twoColumnDetail('Queued jobs', (string) $pendingJobs);
        $this->components->twoColumnDetail('Failed jobs', (string) $failedJobs);

        if ($failedJobs > 0 || ($outbox['failed'] ?? 0) > 0 || ($webhooks['failed'] ?? 0) > 0) {
            $this->warn('Failures detected — investigate before pilot sign-off.');

            return self::FAILURE;
        }

        $this->info('All clear.');

        return self::SUCCESS;
    }
}
