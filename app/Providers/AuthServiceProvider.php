<?php

namespace App\Providers;

use App\Http\Controllers\Auth\IssueClientTokenController;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        $this->registerDefaultPasswordRules();
        $this->registerTokenLifeTimes();

        Passport::cookie('api_token');
    }

    private function registerDefaultPasswordRules(): void
    {
        Password::defaults(function () {
            return Password::min(8)->numbers();
        });
    }

    private function registerTokenLifeTimes(): void
    {
        Passport::tokensExpireIn(now()->addMinutes(30));
        Passport::refreshTokensExpireIn(now()->addDay());

        $this->app->beforeResolving(IssueClientTokenController::class, function () {
            Passport::tokensExpireIn(now()->addMonth());
        });
    }
}
