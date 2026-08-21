<?php

namespace App\Domain\Messaging\Channels;

use App\Models\NotificationOutbox;
use App\Support\Contracts\MessageChannel;
use App\Support\Validation\ContactRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoSmsChannel implements MessageChannel
{
    public function __construct(
        protected string $apiKey,
        protected string $sender,
    ) {}

    public function name(): string
    {
        return 'sms';
    }

    public function send(NotificationOutbox $message): array
    {
        $recipient = ContactRules::toSmsRecipient((string) $message->recipient_phone);
        $sender = $this->senderId();
        $content = trim((string) $message->body);

        if ($recipient === '') {
            throw new RuntimeException('Parent phone is missing or invalid for SMS.');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException('Brevo API key is not configured in Settings.');
        }

        if ($sender === '') {
            throw new RuntimeException('SMS sender ID is missing. Use a registered alphanumeric ID (max 11 characters).');
        }

        if ($content === '') {
            throw new RuntimeException('SMS content is empty.');
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])
            ->asJson()
            ->timeout(45)
            ->post('https://api.brevo.com/v3/transactionalSMS/send', [
                'sender' => $sender,
                'recipient' => $recipient,
                'content' => $content,
                'type' => 'transactional',
                'tag' => $message->event_type ?: 'attendance',
                'unicodeEnabled' => ! preg_match('/^[\x00-\x7F]*$/', $content),
            ]);

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            $detail = $json['message']
                ?? data_get($json, 'error.message')
                ?? $response->body();

            throw new RuntimeException('Brevo SMS send failed: '.$detail);
        }

        $providerId = (string) ($json['messageId'] ?? '');

        if ($providerId === '') {
            throw new RuntimeException(
                'Brevo did not return a message id. Response: '.Str::limit($response->body(), 240)
            );
        }

        return [
            'provider_message_id' => $providerId,
            'cost' => null,
            'status' => 'sent',
        ];
    }

    protected function senderId(): string
    {
        $sender = preg_replace('/[^A-Za-z0-9]/', '', $this->sender) ?? '';
        $max = ctype_digit($sender) ? 15 : 11;

        return substr($sender, 0, $max);
    }
}
