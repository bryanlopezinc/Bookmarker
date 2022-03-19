<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidDomainNameException;

final class DomainName
{
    public readonly string $value;

    public function __construct(string $domainName)
    {
        if (filter_var($domainName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidDomainNameException('Invalid domain name ' . $domainName);
        }

        $this->value = str_replace('www.', '', $domainName);
    }
}
