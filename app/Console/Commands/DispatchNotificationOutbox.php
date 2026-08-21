<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNotificationOutbox;
use App\Models\NotificationOutbox;
use Illuminate\Console\Command;

class DispatchNotificationOutbox extends Command
{
    protected $signature = 'outbox:dispatch {--limit=200}';

    protected $description = 'Dispatch pending notification outbox messages to their channels';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $ids = NotificationOutbox::query()
            ->withoutGlobalScopes()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                ProcessNotificationOutbox::dispatchSync($id);
            } catch (\Throwable $e) {
                $this->warn("Outbox #{$id}: ".$e->getMessage());
            }
        }

        $this->info("Dispatched {$ids->count()} outbox message(s).");

        return self::SUCCESS;
    }
}
