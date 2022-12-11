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

    public function testUrlMustBeHttpOrHttps(): void
    {
        foreach ([
            ' ',
            '/docs/9.x/encryption',
            'git://github.com/user/project-name.git',
            'webcal://example.com/calendar.ics',
            'chrome://flags',
            'ldap://ds.example.com:389',
            'adiumxtra://www.adiumxtras.com/download/0000',
            'dns://192.168.1.1/ftp.example.org?type=A',
            'facetime://+19995551234',
            'feed://example.com/rss.xml',
            'git://github.com/user/project-name.git',
            'lastfm://bryan/king/astro', 'market://details?id=Package_name',
            'payto://iban/DE75512108001245126199?amount=EUR:200.0&message=hello',
            'sgn://social-network.example.com/?ident=bob',
        ] as $url) {
            $this->assertIsValidUrl($url);
        }
    }

    private function assertIsValidUrl(string $url): void
    {
        $passed = true;
        $message = "Failed asserting that [$url] is invalid";

        try {
            new Url($url);
        } catch (MalformedURLException) {
            $passed = false;
        }

        $this->assertFalse($passed, $message);
        $this->assertFalse(Url::isValid($url), $message);
    }

    public function testSerialization(): void
    {
        $url = new Url($this->faker->url);

        $unSerialized = unserialize(serialize($url));

        $this->assertEquals($url, $unSerialized);
    }
}
