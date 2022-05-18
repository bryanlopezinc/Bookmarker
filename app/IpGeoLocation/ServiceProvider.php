<?php

namespace App\IpGeoLocation;

use App\IpGeoLocation\IpApi\IpGeoLocationHttpClient;
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
        $this->app->bind(IpGeoLocatorInterface::class, fn () => app(IpGeoLocationHttpClient::class));
    }

    public function provides(): array
    {
        return [IpGeoLocatorInterface::class];
    }
}
