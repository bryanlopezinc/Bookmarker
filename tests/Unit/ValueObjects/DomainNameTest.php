<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\DomainName;
use PHPUnit\Framework\TestCase;

class DomainNameTest extends TestCase
{
    public function testWillRemove_www_FromDomainName(): void
    {
        $domainName = new DomainName('www.stackoverflow.com');

        $this->assertEquals($domainName->value, 'stackoverflow.com');
    }
}
