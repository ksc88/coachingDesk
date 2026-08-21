<?php

namespace App\Console\Commands;

use App\Domain\Academics\AcademicSessionResolver;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RollAcademicSessions extends Command
{
    protected $signature = 'sessions:roll';

    protected $description = 'Ensure every coaching has the current April-March academic session marked as current';

    public function handle(AcademicSessionResolver $sessions): int
    {
        $label = $sessions->labelFor();
        $created = 0;

        Tenant::query()
            ->where('status', '!=', 'closed')
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($sessions, &$created): void {
                $before = $tenant->academicSessions()->count();
                $sessions->current($tenant->id);

                if ($tenant->academicSessions()->count() > $before) {
                    $created++;
                    $this->line("Created new session for {$tenant->name}");
                }
            });

        $this->info("Session {$label} is current for all coachings ({$created} created).");

        return self::SUCCESS;
    }
}
