<?php

namespace App\Providers;

use App\Domain\Billing\RazorpayGateway;
use App\Domain\Messaging\Channels\LogMessageChannel;
use App\Support\Contracts\FileStorage;
use App\Support\Contracts\MessageChannel;
use App\Support\Contracts\PaymentGateway;
use App\Support\Services\LocalTenantFileStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, RazorpayGateway::class);
        $this->app->bind(MessageChannel::class, fn () => new LogMessageChannel('log'));
        $this->app->bind(FileStorage::class, LocalTenantFileStorage::class);
    }

    public function boot(): void
    {
        //
    }
}
