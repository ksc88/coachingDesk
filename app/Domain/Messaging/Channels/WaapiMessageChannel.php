<?php

namespace App\Domain\Messaging\Channels;

use App\Models\NotificationOutbox;
use App\Support\Contracts\MessageChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WaapiMessageChannel implements MessageChannel
{
    public function __construct(
        protected string $apiToken,
        protected string $instanceId,
    ) {}

    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(NotificationOutbox $message): array
    {
        $chatId = $this->chatIdFromPhone((string) $message->recipient_phone);

        if ($chatId === '') {
            throw new RuntimeException('Parent phone is missing or invalid for WhatsApp.');
        }

        if ($this->apiToken === '' || $this->instanceId === '') {
            throw new RuntimeException('WaAPI token or instance ID is not configured in Settings.');
        }

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post("https://waapi.app/api/v1/instances/{$this->instanceId}/client/action/send-message", [
                'chatId' => $chatId,
                'message' => (string) $message->body,
            ]);

        $json = $response->json() ?? [];
        $apiStatus = strtolower((string) ($json['status'] ?? ''));

        if (! $response->successful() || $apiStatus === 'error') {
            $detail = $json['message']
                ?? $json['error']
                ?? $response->body();

            throw new RuntimeException('WaAPI send failed: '.$detail);
        }

        // Real WaAPI ids live under data._data.id._serialized (not data.id).
        $providerId = (string) (
            data_get($json, 'data._data.id._serialized')
            ?? data_get($json, 'data.id._serialized')
            ?? data_get($json, 'data.id')
            ?? $json['messageId']
            ?? ''
        );

        if ($providerId === '' && $apiStatus !== 'success') {
            throw new RuntimeException(
                'WaAPI did not confirm the message. Response: '.Str::limit($response->body(), 240)
            );
        }

        if ($providerId === '') {
            $providerId = 'waapi_ok_'.$message->id.'_'.time();
        }

        return [
            'provider_message_id' => $providerId,
            'cost' => null,
            'status' => 'sent',
        ];
    }

    /**
     * WaAPI expects chatId like 919876543210@c.us (country code, no + or spaces).
     */
    protected function chatIdFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // Already international (91…) — do not prefix again
        if (str_starts_with($digits, '91') && strlen($digits) >= 10) {
            return $digits.'@c.us';
        }

        // Leading 0 (e.g. 09876…)
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Local Indian 10-digit → add 91
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return $digits.'@c.us';
    }
}
