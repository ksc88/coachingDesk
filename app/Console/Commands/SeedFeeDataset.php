<?php

namespace App\Console\Commands;

use Database\Seeders\IndreshFeeDatasetSeeder;
use Illuminate\Console\Command;

class SeedFeeDataset extends Command
{
    protected $signature = 'fees:seed-dataset
                            {--tenant=INDR : Tenant code (only INDR is supported today)}';

    protected $description = 'Seed all fee plan/payment combinations for UI testing (Indresh / INDR)';

    public function handle(): int
    {
        $code = strtoupper((string) $this->option('tenant'));

        if ($code !== 'INDR') {
            $this->error('Only --tenant=INDR is supported for now.');

            return self::FAILURE;
        }

        $this->info('Seeding fee dataset for Indresh English Classes (INDR)…');

        $seeder = app(IndreshFeeDatasetSeeder::class);
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
