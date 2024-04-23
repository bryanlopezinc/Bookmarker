<?php

declare(strict_types=1);

namespace App\Contracts;

interface IdGeneratorInterface
{
    public const LENGTH = 18;

    /**
     * @return string self::LENGTH characters.
     */
    public function generate(): string;

    /**
     * Assert the given id is valid.
     */
    public function isValid(string $Id): bool;
}
