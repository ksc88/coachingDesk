<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\BillingService;
use App\Domain\Billing\RazorpayGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentWebhookEvent;
use App\Models\Student;
use App\Models\TenantPaymentGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        protected RazorpayGateway $razorpay,
        protected BillingService $billing,
    ) {}

    public function __invoke(Request $request, int $tenantId): Response
    {
        $gateway = TenantPaymentGateway::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', 'razorpay')
            ->firstOrFail();

        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $eventId = $request->header('X-Razorpay-Event-Id') ?: sha1($payload);

        if ($gateway->webhook_secret && ! $this->razorpay->verifyWebhookSignature($gateway, $payload, $signature)) {
            return response('Invalid signature', 400);
        }

        if (PaymentWebhookEvent::query()->where('event_id', $eventId)->exists()) {
            return response('OK', 200);
        }

        $json = $request->all();

        $event = PaymentWebhookEvent::query()->create([
            'tenant_id' => $tenantId,
            'provider' => 'razorpay',
            'event_id' => $eventId,
            'event_type' => $json['event'] ?? null,
            'payload' => $json,
            'status' => 'received',
        ]);

        TenantContext::setId($tenantId);

        try {
            if (($json['event'] ?? null) === 'payment.captured') {
                $entity = data_get($json, 'payload.payment.entity', []);
                $notes = $entity['notes'] ?? [];
                $invoiceId = $notes['invoice_id'] ?? null;
                $studentId = $notes['student_id'] ?? null;

                if ($invoiceId && $studentId) {
                    $invoice = Invoice::query()->withoutGlobalScopes()->find($invoiceId);
                    $student = Student::query()->withoutGlobalScopes()->find($studentId);

                    if ($invoice && $student && (int) $invoice->tenant_id === $tenantId) {
                        $this->billing->recordGatewayPayment($student, [
                            'amount' => ((float) ($entity['amount'] ?? 0)) / 100,
                            'gateway_order_id' => $entity['order_id'] ?? null,
                            'gateway_payment_id' => $entity['id'],
                            'invoice_id' => $invoice->id,
                            'reference' => $entity['id'],
                        ]);
                    }
                }
            }

            $event->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            Log::error('razorpay.webhook.failed', ['error' => $e->getMessage(), 'event_id' => $eventId]);
            $event->update(['status' => 'failed', 'processing_error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }
}
