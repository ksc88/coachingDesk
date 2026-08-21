<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use App\Models\TenantPaymentGateway;
use App\Support\Tenancy\TenantContext;
use RuntimeException;

class TenantGatewayResolver
{
    public function forCurrentTenant(string $provider = 'razorpay'): TenantPaymentGateway
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant context missing for payment gateway resolution.');
        }

        $gateway = TenantPaymentGateway::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            throw new RuntimeException('Active payment gateway not configured for this coaching.');
        }

        return $gateway;
    }

    public function assertMatchesInvoice(TenantPaymentGateway $gateway, Invoice $invoice): void
    {
        if ((int) $gateway->tenant_id !== (int) $invoice->tenant_id) {
            throw new RuntimeException('Gateway tenant does not match invoice tenant.');
        }
    }
}
