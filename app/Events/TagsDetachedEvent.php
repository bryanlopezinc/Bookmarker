<?php

declare(strict_types=1);

namespace App\Events;

use App\Collections\TagsCollection;
use App\ValueObjects\UserID;

final class TagsDetachedEvent
{
    public function __construct(public readonly TagsCollection $tags, public readonly UserID $userID)
    {
    }
}
