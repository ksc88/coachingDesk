<?php

namespace App\Jobs;

use App\Domain\Messaging\Channels\BrevoEmailChannel;
use App\Domain\Messaging\Channels\BrevoSmsChannel;
use App\Domain\Messaging\Channels\LogMessageChannel;
use App\Domain\Messaging\Channels\WaapiMessageChannel;
use App\Models\NotificationOutbox;
use App\Models\Tenant;
use App\Support\Contracts\MessageChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;

class ProcessNotificationOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $outboxId) {}

    public function handle(): void
    {
        $message = NotificationOutbox::query()->withoutGlobalScopes()->find($this->outboxId);
        if (! $message || in_array($message->status, ['sent', 'failed'], true)) {
            return;
        }

        $maxAttempts = (int) config('coaching.alerts.max_attempts', 5);
        $message->increment('attempts');
        $message->refresh();

        $tenant = Tenant::query()->find($message->tenant_id);
        $live = (bool) $tenant?->alertsAreLive();

        try {
            $channel = $this->resolveChannel($message->channel, $live, $tenant);
            $result = $channel->send($message);

            $payload = $message->payload ?? [];
            $payload['delivery_mode'] = $live ? 'live' : 'safe';
            $payload['provider'] = $this->providerName($message->channel, $live, $tenant);

            $message->update([
                'status' => $result['status'] ?? 'sent',
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'cost' => $result['cost'] ?? null,
                'sent_at' => now(),
                'failure_reason' => null,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            $permanent = $message->attempts >= $maxAttempts;

            $message->update([
                'status' => $permanent ? 'failed' : 'pending',
                'failure_reason' => $e->getMessage(),
            ]);

            if ($permanent) {
                return;
            }

            throw $e;
        }
    }

    protected function resolveChannel(string $name, bool $live, ?Tenant $tenant): MessageChannel
    {
        if (! $live) {
            return new LogMessageChannel($name);
        }

        if ($name === 'whatsapp') {
            $alerts = $tenant?->settings['alerts'] ?? [];
            $provider = strtolower(trim((string) ($alerts['whatsapp_provider'] ?? '')));
            $token = trim((string) ($alerts['whatsapp_token'] ?? ''));
            $instanceId = trim((string) ($alerts['whatsapp_from'] ?? ''));

            if (in_array($provider, ['waapi', 'wa-api', 'waapi.app'], true) && $token !== '' && $instanceId !== '') {
                return new WaapiMessageChannel($token, $instanceId);
            }

            throw new \RuntimeException(
                'Live WhatsApp needs provider = waapi, Instance ID, and Access token in Settings → Parent alerts.'
            );
        }

        if ($name === 'sms') {
            $alerts = $tenant?->settings['alerts'] ?? [];
            $provider = strtolower(trim((string) ($alerts['sms_provider'] ?? 'brevo')));
            $apiKey = trim((string) ($alerts['sms_api_key'] ?? ''));
            $sender = trim((string) ($alerts['sms_sender'] ?? ''));

            if (in_array($provider, ['brevo', 'sendinblue'], true) && $apiKey !== '' && $sender !== '') {
                return new BrevoSmsChannel($apiKey, $sender);
            }

            throw new \RuntimeException(
                'Live SMS needs provider = brevo, Sender ID, and API key in Settings → Parent alerts.'
            );
        }

        if ($name === 'email') {
            $alerts = $tenant?->settings['alerts'] ?? [];
            $provider = strtolower(trim((string) ($alerts['email_provider'] ?? 'brevo')));
            $apiKey = trim((string) ($alerts['email_api_key'] ?? $alerts['sms_api_key'] ?? ''));
            $from = trim((string) ($alerts['email_from'] ?? $tenant?->email ?? ''));
            $fromName = trim((string) ($alerts['email_from_name'] ?? $tenant?->name ?? ''));

            if (in_array($provider, ['brevo', 'sendinblue'], true) && $apiKey !== '' && $from !== '') {
                return new BrevoEmailChannel($apiKey, $from, $fromName);
            }

            throw new \RuntimeException(
                'Live email needs a verified From address and Brevo API key in Settings → Parent alerts.'
            );
        }

        return match ($name) {
            'push' => new LogMessageChannel($name),
            default => App::make(MessageChannel::class),
        };
    }

    protected function providerName(string $channel, bool $live, ?Tenant $tenant): string
    {
        if (! $live) {
            return 'log';
        }

        $alerts = $tenant?->settings['alerts'] ?? [];

        return match ($channel) {
            'whatsapp' => strtolower(trim((string) ($alerts['whatsapp_provider'] ?? 'log'))) ?: 'log',
            'sms' => strtolower(trim((string) ($alerts['sms_provider'] ?? 'brevo'))) ?: 'brevo',
            'email' => strtolower(trim((string) ($alerts['email_provider'] ?? 'brevo'))) ?: 'brevo',
            default => 'log',
        };
    }
}
