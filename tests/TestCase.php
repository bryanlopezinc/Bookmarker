<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Event::listen(MigrationsEnded::class, function () {
        //     $this->seed();
        // });
    }

    final protected function loginUser(Authenticatable $user): void
    {
        Passport::actingAs($user);
    }

    final protected function assertRouteIsAccessibleViaPath(string $path, string $routeName): void
    {
        $this->assertTrue(Route::has($routeName));
        $this->assertEquals(Route::getRoutes()->getByName($routeName)->uri(), $path);
    }
}
