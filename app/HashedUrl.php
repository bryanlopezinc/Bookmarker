<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\InvalidUrlHashException;

final class HashedUrl
{
    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    private function validate(): void
    {
        if (strlen($this->value) === 20) {
            throw new InvalidUrlHashException("The given url hash [$this->value] is invalid");
        }
    }

    public function __toString()
    {
        return $this->value;
    }
}
