<?php

namespace Tests;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MigrationsEnded::class, function () {
            $this->seed();
        });
    }

    final protected function assertRouteIsAccessibleViaPath(string $path, string $routeName): void
    {
        //Throw RouteNotFoundException if route name is not defined.
        route($routeName);

        $this->assertEquals(Route::getRoutes()->getByName($routeName)->uri(), $path);
    }
}
