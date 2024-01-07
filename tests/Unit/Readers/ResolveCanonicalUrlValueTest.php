<?php

declare(strict_types=1);

namespace Tests\Unit\Readers;

use App\Readers\ResolveCanonicalUrlValue;
use App\ValueObjects\Url;
use PHPUnit\Framework\TestCase;

class ResolveCanonicalUrlValueTest extends TestCase
{
    public function testWillReturnCorrectValueWhenUrl_IsRelative(): void
    {
        $resolver = new ResolveCanonicalUrlValue('/trending/php', new Url('https://github.com/trending/php'));
        $this->assertEquals('https://github.com/trending/php', $resolver()->toString());

        $resolver = new ResolveCanonicalUrlValue('/trending/php', new Url('https://github.com/trending/php?since=daily&spoken_language_code=en'));
        $this->assertEquals('https://github.com/trending/php', $resolver()->toString());
    }

    public function testWillReturnFalseWhenRelativePathIsNotSame(): void
    {
        $resolver = new ResolveCanonicalUrlValue('/explore', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());
    }

    public function testWillReturnFalseWhenDomainIsNotSame(): void
    {
        $resolver = new ResolveCanonicalUrlValue(
            'https://ogs.google.com/widget/app',
            new Url('https://www.google.com/search?q=og%3Aurl&oq=og%3Aurl&aqs=chrome.0.69i59l2j69i58j69i61.4189j0j7&sourceid=chrome&ie=UTF-8')
        );

        $this->assertFalse($resolver());
    }

    public function testWillReturnFalseWhenUrlHasNoPath(): void
    {
        $resolver = new ResolveCanonicalUrlValue('https://github.com', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());

        $resolver = new ResolveCanonicalUrlValue('https://www.github.com', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());

        $resolver = new ResolveCanonicalUrlValue('http://github.com', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());

        $resolver = new ResolveCanonicalUrlValue('https://github.com/', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());
    }

    public function testWillReturnFalseWhenUrlIsInvalid(): void
    {
        $resolver = new ResolveCanonicalUrlValue('foobar', new Url('https://github.com/trending/php'));
        $this->assertFalse($resolver());
    }

    public function testWillReturnCorrectUrl(): void
    {
        $resolver = new ResolveCanonicalUrlValue('https://github.com/trending/php', new Url('https://github.com/trending/php'));
        $this->assertEquals($resolver()->toString(), 'https://github.com/trending/php');

        $resolver = new ResolveCanonicalUrlValue('https://github.com', new Url('https://github.com'));
        $this->assertEquals($resolver()->toString(), 'https://github.com');

        $resolver = new ResolveCanonicalUrlValue('https://github.com/trending/php', new Url('https://github.com/trending/php?since=daily&spoken_language_code=en'));
        $this->assertEquals('https://github.com/trending/php', $resolver()->toString());
    }
}
