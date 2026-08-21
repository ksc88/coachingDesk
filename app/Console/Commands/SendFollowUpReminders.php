<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature = 'enquiries:remind';

    protected $description = 'List enquiries whose follow-up is due so staff can be reminded';

    public function handle(): int
    {
        $due = Enquiry::query()
            ->withoutGlobalScopes()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereNotIn('status', ['admitted', 'lost'])
            ->get();

        foreach ($due as $enquiry) {
            $this->line("Follow-up due: {$enquiry->name} ({$enquiry->phone}) — owner #{$enquiry->owner_id}");
        }

        $this->info("{$due->count()} follow-up(s) due.");

        return self::SUCCESS;
    }
}
