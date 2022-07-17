<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UrlTest extends TestCase
{
    use WithFaker;

    public function testWillThrowExceptionWhenUrlIsInvalid(): void
    {
        $this->expectException(MalformedURLException::class);

        new Url('foo');
    }

    public function test_isValid_willReturnFalseWhenUrlIsInvalid(): void
    {
        $this->assertFalse(Url::isValid('foo'));
    }

    public function test_isValid_willReturnTrueWhenUrlIsValid(): void
    {
        $this->assertTrue(Url::isValid($this->faker->url));
    }

    public function testGetHostName(): void
    {
        $url = new Url('https://laravel.com/docs/9.x/encryption');

        $this->assertEquals('laravel.com', $url->getHost());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('/docs/9.x/encryption', (new Url('https://laravel.com/docs/9.x/encryption'))->getPath());
        $this->assertEquals('/', (new Url('https://laravel.com'))->getPath());
    }

    public function testParseQuery(): void
    {
        $this->assertEquals([], (new Url('https://laravel.com/docs/9.x/encryption?'))->parseQuery());
        $this->assertEquals([], (new Url('https://laravel.com/docs/9.x/encryption'))->parseQuery());

        $url = new Url('https://laravel.com/docs/9.x/encryption?foo=bar&action=nav_bar&utm_source=bryan');

        $expected = [
            'foo' => 'bar',
            'action' => 'nav_bar',
            'utm_source' => 'bryan',
        ];

        $this->assertEquals($expected, $url->parseQuery());
    }
}
