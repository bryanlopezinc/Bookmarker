<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\Folder;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\PositiveNumber;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Carbon\Carbon;

final class FolderBuilder extends Builder
{
    public function setID(int $id): self
    {
        $this->attributes['folderID'] =  new ResourceID($id);

        return $this;
    }

    public function setOwnerID(int|UserID $id): self
    {
        $this->attributes['ownerID'] = $id instanceof UserID ? $id : new UserID($id);

        return $this;
    }

    public function setName(string $name): self
    {
        $this->attributes['name'] = new FolderName($name);

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->attributes['description'] = new FolderDescription($description);

        return $this;
    }

    public function setCreatedAt(Carbon $date): self
    {
        $this->attributes['createdAt'] = $date;

        return $this;
    }

    public function setUpdatedAt(Carbon $date): self
    {
        $this->attributes['updatedAt'] = $date;

        return $this;
    }

    public function setBookmarksCount(int $count): self
    {
        $this->attributes['bookmarksCount'] = new PositiveNumber($count);

        return $this;
    }

    public function build(): Folder
    {
        return new Folder($this->attributes);
    }
}
