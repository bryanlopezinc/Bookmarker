<?php

declare(strict_types=1);

namespace App\Providers;

use App\Readers\Factory;
use App\Readers\HttpClientInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Tests\TestHttpClient;

class ReaderServiceProvider extends BaseProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(HttpClientInterface::class, function (Application $app) {
            if ($app->runningUnitTests()) {
                return new TestHttpClient();
            }

            return new Factory();
        });
    }

    public function provides(): array
    {
        return [HttpClientInterface::class,];
    }
}
