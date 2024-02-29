<?php

namespace App\DeviceDetector;

use App\Contracts\DeviceDetectorInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as Provider;

class ServiceProvider extends Provider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(DeviceDetectorInterface::class, fn () => new DeviceDetector());
    }

    public function provides(): array
    {
        return [DeviceDetectorInterface::class];
    }
}
