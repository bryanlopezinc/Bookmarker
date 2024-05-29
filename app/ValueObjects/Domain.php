<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Exception;
use Utopia\Domains\Domain as DomainParser;

final class Domain
{
    private readonly DomainParser $parser;

    public function __construct(string $domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new Exception("invalid domain [{$domain}].");
        }

        $this->parser = new DomainParser($domain);
    }

    /**
     * Returns registerable domain name
     */
    public function getRegisterable(): string
    {
        return $this->parser->getRegisterable();
    }

    /**
     * Returns registerable domain name hash
     */
    public function getRegisterableHash(): string
    {
        return hash('xxh3', $this->getRegisterable());
    }
}
