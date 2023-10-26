<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\UrlPlaceholders;
use PHPUnit\Framework\TestCase;

class UrlPlaceholdersTest extends TestCase
{
    public function testCheckMethod(): void
    {
        $this->assertEquals([], UrlPlaceholders::missing('https://laravel.com/:foo/docs', [':foo']));
        $this->assertEquals([], UrlPlaceholders::missing('https://laravel.com?place=:foo&bar=baz', [':foo']));
        $this->assertEquals([], UrlPlaceholders::missing('https://laravel.com/:foo?place=foo&bar=:baz', [':foo', ':baz']));
        $this->assertEquals([':foo'], UrlPlaceholders::missing('https://laravel.com?place=foo&bar=baz', [':foo']));
    }
}
