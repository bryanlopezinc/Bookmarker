<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /** @var bool */
        $preventLazyLoading = $this->app->environment('local', 'testing');

        Model::preventLazyLoading($preventLazyLoading);

        Model::preventAccessingMissingAttributes();

        Relation::enforceMorphMap([
            'user' => \App\Models\User::class
        ]);
    }
}
