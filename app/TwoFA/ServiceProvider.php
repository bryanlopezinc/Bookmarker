<?php

namespace App\TwoFA;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Laravel\Passport\Bridge\UserRepository;

class ServiceProvider extends BaseProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(VerificationCodeGeneratorInterface::class, RandomNumberCodeGenerator::class);
    }

    public function provides(): array
    {
        return [
            VerificationCodeGeneratorInterface::class,
        ];
    }
}
