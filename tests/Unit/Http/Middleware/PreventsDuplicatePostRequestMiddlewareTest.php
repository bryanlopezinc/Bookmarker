<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\PreventsDuplicatePostRequestMiddleware as Middleware;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\HeaderBag;
use Tests\TestCase;

class PreventsDuplicatePostRequestMiddlewareTest extends TestCase
{
    #[Test]
    public function willOnlyHandlePostMethod(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->never())->method('has');
            $mock->expects($this->never())->method('put');
        });

        $middleware = new Middleware($cache);

        $request = $this->getRequest();
        $request->setMethod('GET');
        $middleware->handle($request, fn () => new Response());

        $request = $this->getRequest();
        $request->setMethod('PATCH');
        $middleware->handle($request, fn () => new Response());

        $request = $this->getRequest();
        $request->setMethod('PUT');
        $middleware->handle($request, fn () => new Response());

        $request = $this->getRequest();
        $request->setMethod('DELETE');
        $middleware->handle($request, fn () => new Response());
    }

    #[Test]
    public function headerMustNotBeGreaterThan_64_Chars(): void
    {
        $this->expectExceptionMessage('The idempotency key must not be greater than 64 characters.');

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'POST',
                'HTTP_idempotency_key' => str_repeat('F', 65)
            ]
        );

        $middleware = new Middleware();

        $middleware->handle($request, fn () => new Response());
    }

    #[Test]
    public function willNotCacheResultWhenIdempotencyKeyIsMissing(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->never())->method('has');
            $mock->expects($this->never())->method('put');
        });

        $request = $this->getRequest();
        $request->headers = new HeaderBag();

        $middleware = new Middleware($cache);

        $middleware->handle($request, fn () => new Response('', 403));
    }

    #[Test]
    public function whenIdExistsInCache(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->once())->method('has')->willReturn(true);
            $mock->expects($this->never())->method('put');
            $mock->expects($this->once())->method('get')->willReturn(new Response(status: 403));
        });

        $middleware = new Middleware($cache);
        $response = $middleware->handle($this->getRequest(), fn () => new Response());

        $this->assertEquals(403, $response->status());
    }

    /**
     * @return Repository
     */
    private function getCacheMock(\Closure $expectation)
    {
        $expectation($cache = $this->getMockBuilder(Repository::class)->getMock());

        return $cache;
    }

    private function getRequest()
    {
        return new Request(
            server: [
                'REQUEST_METHOD' => 'POST',
                'HTTP_idempotency_key' => 'a3a9ca1c-b738-3329-ac76-dfa19933c2e9'
            ]
        );
    }

    #[Test]
    public function willNotCacheRequestIdWhenResponseIsAValidationErrorResponse(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->any())->method('has')->willReturn(false);
            $mock->expects($this->never())->method('put');
        });

        $middleware = new Middleware($cache);

        $response = $middleware->handle($this->getRequest(), fn () => new Response('', 422));
        $this->assertEquals(422, $response->status());
    }

    #[Test]
    public function willCacheResponse(): void
    {
        $cache = $this->getCacheMock(function ($mock) {
            $mock->expects($this->any())->method('has')->willReturn(false);
            $mock->expects($this->once())
                ->method('put')
                ->willReturnCallback(function (string $key, Response $response) {
                    $this->assertEquals(201, $response->status());
                    $this->assertEquals('a3a9ca1c-b738-3329-ac76-dfa19933c2e9', $key);
                });
        });

        $middleware = new Middleware($cache);

        $response = $middleware->handle($this->getRequest(), fn () => new Response('', 201));
        $this->assertEquals(201, $response->status());
    }
}
