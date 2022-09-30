<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\FolderStorage;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Carbon\Carbon;

final class FolderBuilder extends Builder
{
    public static function fromModel(Model $folder): self
    {
        $attributes = $folder->toArray();

        $keyExists = fn (string $key) => array_key_exists($key, $attributes);

        return (new self)
            ->when($keyExists('id'), fn (FolderBuilder $b) => $b->setID($folder->id))
            ->when($keyExists('user_id'), fn (FolderBuilder $b) => $b->setOwnerID($folder->user_id))
            ->when($keyExists('name'), fn (FolderBuilder $b) => $b->setName($folder->name))
            ->when($keyExists('description'), fn (FolderBuilder $b) => $b->setDescription($folder->description))
            ->when($keyExists('created_at'), fn (FolderBuilder $b) => $b->setCreatedAt($folder->created_at))
            ->when($keyExists('updated_at'), fn (FolderBuilder $b) => $b->setUpdatedAt($folder->updated_at))
            ->when($keyExists('bookmarks_count'), fn (FolderBuilder $b) => $b->setBookmarksCount((int)$folder->bookmarks_count)) // @phpstan-ignore-line
            ->when($keyExists('is_public'), fn (FolderBuilder $b) => $b->setisPublic($folder->is_public))
            ->when($keyExists('tags'), fn (FolderBuilder $b) => $b->setTags($folder->getRelation('tags')->all()));
    }

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
        $this->attributes['storage'] = new FolderStorage($count);

        return $this;
    }

    public function setisPublic(bool $isPublic): self
    {
        $this->attributes['isPublic'] = $isPublic;

        return $this;
    }

    public function setTags(TagsCollection|array $tags): self
    {
        $this->attributes['tags'] = is_array($tags) ? TagsCollection::make($tags) : $tags;

        return $this;
    }

    public function build(): Folder
    {
        return new Folder($this->attributes);
    }
}
