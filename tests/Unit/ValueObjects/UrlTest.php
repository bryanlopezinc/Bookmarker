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

    public function testUrlMustBeAbsolute(): void
    {
        $this->expectException(MalformedURLException::class);

        new Url('/docs/9.x/encryption');
    }

    public function testIsHttp(): void
    {
        $this->assertTrue((new Url('http://laravel.com/docs/9.x/encryption'))->isHttp());
        $this->assertFalse((new Url('https://laravel.com/docs/9.x/encryption'))->isHttp());
        $this->assertFalse((new Url('chrome://flags'))->isHttp());
        $this->assertFalse((new Url('webcal://example.com/calendar.ics'))->isHttp());
    }

    public function testIsHttps(): void
    {
        $this->assertFalse((new Url('http://laravel.com/docs/9.x/encryption'))->isHttps());
        $this->assertTrue((new Url('https://laravel.com/docs/9.x/encryption'))->isHttps());
        $this->assertFalse((new Url('chrome://flags'))->isHttps());
        $this->assertFalse((new Url('webcal://example.com/calendar.ics'))->isHttps());
    }

    public function testIsValid(): void
    {
        $this->assertFalse(Url::isValid(''));
        $this->assertTrue(Url::isValid($this->faker->url));
        $this->assertTrue(Url::isValid('http://laravel.com/docs/9.x/encryption'));
        $this->assertFalse(Url::isValid('/docs/9.x/encryption'));

        foreach ([
            'chrome://flags', 'adiumxtra://www.adiumxtras.com/download/0000',
            'dns://192.168.1.1/ftp.example.org?type=A', 'facetime://+19995551234', 'feed://example.com/rss.xml',
            'git://github.com/user/project-name.git', 'lastfm://bryan/king/astro', 'market://details?id=Package_name',
            'payto://iban/DE75512108001245126199?amount=EUR:200.0&message=hello',
            'sgn://social-network.example.com/?ident=bob',
            'webcal://example.com/calendar.ics',
        ] as $url) {
            $this->assertTrue(Url::isValid($url), "Failed asserting that  [$url] is a valid url");
        }
    }

    public function testSerialization(): void
    {
        $url = new Url('git://github.com/user/project-name.git');

        $unserialized = unserialize(serialize($url));

        $this->assertEquals($url, $unserialized);
    }
}
