<?php

declare(strict_types=1);

namespace App\Contracts;

use Stringable;
use App\Exceptions\InvalidUrlHashException;

interface HashedUrlInterface extends Stringable
{
    /**
     * Create a new instance of HashedUrlInterface
     *
     * @throws InvalidUrlHashException
     */
    public function make(string $hash): HashedUrlInterface;
}
