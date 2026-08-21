<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentWebhookEvent;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeFeeData extends Command
{
    protected $signature = 'fees:wipe
                            {--tenant= : Tenant code (e.g. INDR). Omit to wipe ALL tenants}
                            {--force : Skip confirmation}';

    protected $description = 'Delete invoices, payments, allocations, receipts (keeps students/batches/fee arrangements)';

    public function handle(TenantContext $tenancy): int
    {
        $code = $this->option('tenant');
        $tenant = null;

        if ($code) {
            $tenant = Tenant::query()->where('code', strtoupper($code))->first();
            if (! $tenant) {
                $this->error("Tenant not found: {$code}");

                return self::FAILURE;
            }
            $tenancy->set($tenant);
            $this->warn("Will wipe fee data for: {$tenant->name} ({$tenant->code})");
        } else {
            $this->warn('Will wipe fee data for ALL tenants.');
        }

        if (! $this->option('force') && ! $this->confirm('Continue? This cannot be undone.', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $scope = function ($query) use ($tenant) {
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }

            return $query;
        };

        $counts = DB::transaction(function () use ($scope, $tenant) {
            $paymentIds = $scope(Payment::withoutGlobalScopes())->pluck('id');

            $alloc = PaymentAllocation::query()
                ->when($tenant, fn ($q) => $q->whereIn('payment_id', $paymentIds))
                ->delete();

            $rec = $scope(Receipt::withoutGlobalScopes())->delete();
            $pay = $scope(Payment::withoutGlobalScopes())->delete();
            $inv = $scope(Invoice::withoutGlobalScopes())->delete();
            $seq = $scope(ReceiptSequence::withoutGlobalScopes())->update(['last_number' => 0]);

            $wh = 0;
            if (Schema::hasTable('payment_webhook_events')) {
                $wh = $scope(PaymentWebhookEvent::withoutGlobalScopes())->delete();
            }

            return compact('alloc', 'rec', 'pay', 'inv', 'seq', 'wh');
        });

        $this->info(sprintf(
            'Deleted allocations=%d receipts=%d payments=%d invoices=%d webhooks=%d; receipt sequences reset (%d rows).',
            $counts['alloc'],
            $counts['rec'],
            $counts['pay'],
            $counts['inv'],
            $counts['wh'],
            $counts['seq'],
        ));

        return self::SUCCESS;
    }
}
