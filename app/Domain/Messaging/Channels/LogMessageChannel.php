<?php

namespace App\Domain\Messaging\Channels;

use App\Models\NotificationOutbox;
use App\Support\Contracts\MessageChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogMessageChannel implements MessageChannel
{
    public function __construct(protected string $channelName = 'log') {}

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(NotificationOutbox $message): array
    {
        $id = $this->channelName.'_'.Str::uuid();

        Log::info('notification.sent', [
            'channel' => $this->channelName,
            'to' => $message->recipient_phone ?? $message->recipient_email,
            'body' => $message->body,
            'provider_message_id' => $id,
        ]);

        return [
            'provider_message_id' => $id,
            'cost' => 0,
            'status' => 'sent',
        ];
    }
}
