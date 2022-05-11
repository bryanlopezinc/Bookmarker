<?php

namespace App\Providers;

use App\Readers\Factory;
use App\Readers\HttpClientInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ReaderServiceProvider extends BaseProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(HttpClientInterface::class, Factory::class);
    }

    public function provides(): array
    {
        return [
            HttpClientInterface::class,
        ];
    }
}
