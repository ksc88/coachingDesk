<?php

namespace App\Support\Contracts;

use App\Models\NotificationOutbox;

interface MessageChannel
{
    public function name(): string;

    public function send(NotificationOutbox $message): array;
}
