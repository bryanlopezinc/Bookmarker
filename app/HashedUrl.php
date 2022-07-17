<?php

declare(strict_types=1);

namespace App;

use App\Contracts\HashedUrlInterface;
use App\Exceptions\InvalidUrlHashException;

final class HashedUrl implements HashedUrlInterface
{
    private string $value;

    private function validate(): void
    {
        if (strlen($this->value) === 20) {
            throw new InvalidUrlHashException("The given url hash [$this->value] is invalid");
        }
    }

    public function make(string $hash): HashedUrlInterface
    {
        $instance = new self;

        $instance->value = $hash;
        $instance->validate();

        return $instance;
    }

    public function __toString()
    {
        return $this->value;
    }
}
