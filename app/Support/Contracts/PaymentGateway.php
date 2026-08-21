<?php

namespace App\Support\Contracts;

use App\Models\Invoice;
use App\Models\TenantPaymentGateway;

interface PaymentGateway
{
    public function createOrder(TenantPaymentGateway $gateway, Invoice $invoice, int $amountPaise): array;

    public function verifyPaymentSignature(TenantPaymentGateway $gateway, array $payload): bool;

    public function fetchPayment(TenantPaymentGateway $gateway, string $paymentId): array;
}
