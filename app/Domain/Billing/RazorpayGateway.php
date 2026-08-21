<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use App\Models\TenantPaymentGateway;
use App\Support\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RazorpayGateway implements PaymentGateway
{
    public function createOrder(TenantPaymentGateway $gateway, Invoice $invoice, int $amountPaise): array
    {
        if ($gateway->mode === 'test' && blank($gateway->key_id)) {
            return [
                'id' => 'order_demo_'.Str::random(10),
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $invoice->invoice_no,
                'demo' => true,
            ];
        }

        $response = Http::withBasicAuth((string) $gateway->key_id, (string) $gateway->key_secret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $invoice->invoice_no,
                'notes' => [
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                ],
            ]);

        $response->throw();

        return $response->json();
    }

    public function verifyPaymentSignature(TenantPaymentGateway $gateway, array $payload): bool
    {
        $orderId = $payload['razorpay_order_id'] ?? '';
        $paymentId = $payload['razorpay_payment_id'] ?? '';
        $signature = $payload['razorpay_signature'] ?? '';

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $gateway->key_secret);

        return hash_equals($expected, $signature);
    }

    public function fetchPayment(TenantPaymentGateway $gateway, string $paymentId): array
    {
        if (str_starts_with($paymentId, 'pay_demo_')) {
            return ['id' => $paymentId, 'status' => 'captured', 'amount' => 0];
        }

        $response = Http::withBasicAuth((string) $gateway->key_id, (string) $gateway->key_secret)
            ->get('https://api.razorpay.com/v1/payments/'.$paymentId);

        $response->throw();

        return $response->json();
    }

    public function verifyWebhookSignature(TenantPaymentGateway $gateway, string $payload, ?string $signature): bool
    {
        if (! $signature || ! $gateway->webhook_secret) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, (string) $gateway->webhook_secret);

        return hash_equals($expected, $signature);
    }
}
