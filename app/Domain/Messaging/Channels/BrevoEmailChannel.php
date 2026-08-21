<?php

namespace App\Domain\Messaging\Channels;

use App\Models\NotificationOutbox;
use App\Support\Contracts\MessageChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoEmailChannel implements MessageChannel
{
    public function __construct(
        protected string $apiKey,
        protected string $fromEmail,
        protected string $fromName = '',
    ) {}

    public function name(): string
    {
        return 'email';
    }

    public function send(NotificationOutbox $message): array
    {
        $to = trim((string) $message->recipient_email);
        $body = trim((string) $message->body);
        $from = trim($this->fromEmail);

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Parent email is missing or invalid.');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException('Brevo API key is not configured in Settings.');
        }

        if ($from === '' || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email from address is missing. Use a verified sender from Brevo → Senders.');
        }

        if ($body === '') {
            throw new RuntimeException('Email content is empty.');
        }

        $sender = ['email' => $from];
        $name = trim($this->fromName);
        if ($name !== '') {
            $sender['name'] = Str::limit($name, 70, '');
        }

        $toPayload = ['email' => $to];
        if (filled($message->recipient_name)) {
            $toPayload['name'] = Str::limit((string) $message->recipient_name, 70, '');
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])
            ->asJson()
            ->timeout(45)
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => $sender,
                'to' => [$toPayload],
                'subject' => $this->subjectFor($message),
                'textContent' => $body,
                'htmlContent' => '<html><body><p>'.nl2br(e($body)).'</p></body></html>',
                'tags' => array_values(array_filter([(string) ($message->event_type ?: 'attendance')])),
            ]);

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            $detail = $json['message']
                ?? data_get($json, 'error.message')
                ?? $response->body();

            throw new RuntimeException('Brevo email send failed: '.$detail);
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

    protected function subjectFor(NotificationOutbox $message): string
    {
        $student = trim((string) data_get($message->payload, 'student_name', ''));
        $label = match ($message->event_type) {
            'attendance.absent' => 'Absent alert',
            'attendance.present' => 'Present alert',
            'announcement' => 'Announcement',
            default => 'Parent alert',
        };

        return $student !== '' ? "{$label}: {$student}" : $label;
    }
}
