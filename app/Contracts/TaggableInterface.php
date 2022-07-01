<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\TaggableType;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

interface TaggableInterface
{
    public function taggableID(): ResourceID;

    public function taggableType(): TaggableType;

    public function taggedBy(): UserID;
}
