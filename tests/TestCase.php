<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Additional parameters for the request.
     */
    protected array $parameters = [];

    /**
     * @return static
     */
    protected function withRequestId(string $requestId = null)
    {
        $this->parameters['request_id'] = $requestId ?: fake()->uuid;

        return $this;
    }

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

    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $data = array_merge($data, $this->parameters);

        return parent::json($method, $uri, $data, $headers, $options);
    }

    final protected function assertRequestAlreadyCompleted(TestResponse $response = null): TestResponse
    {
        return $response ?: self::$latestResponse
            ->assertOk()
            ->assertJsonPath('message', 'RequestAlreadyCompleted');
    }
}
