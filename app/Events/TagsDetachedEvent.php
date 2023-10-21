<?php

declare(strict_types=1);

namespace App\Events;

use LogicException;

final class TagsDetachedEvent
{
    /**
     * @param array<string>
     */
    public function __construct(public readonly array $tags)
    {
        if (empty($tags)) {
            throw new LogicException('Tags cannot be empty');
        }
    }
}
