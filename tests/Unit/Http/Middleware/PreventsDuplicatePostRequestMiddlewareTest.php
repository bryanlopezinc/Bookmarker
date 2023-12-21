<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\PreventsDuplicatePostRequestMiddleware as Middleware;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PreventsDuplicatePostRequestMiddlewareTest extends TestCase
{
    #[Test]
    public function willThrowExceptionWhenRequestMethodIsNotPost(): void
    {
        $this->expectExceptionMessage('Cannot use middleware in GET request');

        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->never())->method('has');
            $mock->expects($this->never())->method('put');
        });

        $middleware = new Middleware($cache, $this->getValidatorFactory(), $this->getApplicationInstance());

        $middleware->handle(new Request(server: ['REQUEST_METHOD' => 'GET']), fn () => new Response());
    }

    #[Test]
    public function whenRequestIdExistsInCache(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->once())->method('has')->willReturn(true);
            $mock->expects($this->never())->method('put');
        });

        $middleware = new Middleware($cache, $this->getValidatorFactory(), $this->getApplicationInstance());
        $response = $middleware->handle($this->getRequest(), fn () => new Response());

        $this->assertEquals(200, $response->status());
        $this->assertEquals($response->getData(true), [
            'message'    => 'RequestAlreadyCompleted',
            'info'       => 'A request with the provider request id has already been completed.',
            'request_id' => 'a3a9ca1c-b738-3329-ac76-dfa19933c2e9'
        ]);
    }

    /**
     * @return Repository
     */
    private function getCacheMock(\Closure $expectation)
    {
        $expectation($cache = $this->getMockBuilder(Repository::class)->getMock());

        return $cache;
    }

    /**
     * @return Application
     */
    private function getApplicationInstance()
    {
        $app = $this->getMockBuilder(Application::class)->getMock();

        $app->method('environment')->willReturn(false);

        return $app;
    }

    /**
     * @return Factory
     */
    private function getValidatorFactory()
    {
        $validatorFactory = $this->getMockBuilder(Factory::class)->getMock();
        $validator = $this->getMockBuilder(Validator::class)->getMock();

        $validator->method('validate')->willReturn(['request_id' => 'a3a9ca1c-b738-3329-ac76-dfa19933c2e9']);
        $validatorFactory->method('make')->willReturn($validator);

        return $validatorFactory;
    }

    private function getRequest()
    {
        return new Request(['request_id' => 'a3a9ca1c-b738-3329-ac76-dfa19933c2e9'], server: ['REQUEST_METHOD' => 'POST']);
    }

    #[Test]
    public function willNotCacheRequestIdWhenResponseWasNotSuccessful(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->any())->method('has')->willReturn(false);
            $mock->expects($this->never())->method('put');
        });

        $middleware = new Middleware($cache, $this->getValidatorFactory(), $this->getApplicationInstance());

        $response = $middleware->handle($request = $this->getRequest(), fn () => new Response('', 404));
        $this->assertEquals(404, $response->status());

        $response = $middleware->handle($request, fn () => new Response('', 400));
        $this->assertEquals(400, $response->status());

        $response = $middleware->handle($request, fn () => new Response('', 403));
        $this->assertEquals(403, $response->status());
    }

    #[Test]
    public function willCacheRequestId(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->any())->method('has')->willReturn(false);
            $mock->expects($this->once())
                ->method('put')
                ->willReturnCallback(function (string $key) {
                    $this->assertEquals('a3a9ca1c-b738-3329-ac76-dfa19933c2e9', $key);
                });
        });

        $middleware = new Middleware($cache, $this->getValidatorFactory(), $this->getApplicationInstance());

        $response = $middleware->handle($this->getRequest(), fn () => new Response('', 200));
        $this->assertEquals(200, $response->status());
    }
}
