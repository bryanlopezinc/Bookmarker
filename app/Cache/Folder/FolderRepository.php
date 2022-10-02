<?php

declare(strict_types=1);

namespace App\Cache\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder as Builder;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes;
use App\DataTransferObjects\Folder;
use Illuminate\Contracts\Cache\Repository;

final class FolderRepository implements FolderRepositoryInterface
{
    public function __construct(private FolderRepositoryInterface $repository, private Repository $cache)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function find(ResourceID $folderID, FolderAttributes $attributes = null): Folder
    {
        $key = $this->key($folderID);
        $attributes = $attributes ?: new FolderAttributes();

        if ($this->cache->has($key)) {
            return $this->filter($this->cache->get($key), $attributes);
        }

        //The ENTIRE folder response should be cached and not the filtered
        //version to maintain consistent output.
        // If a filtered version of a folder (such as folder with only attributes id,userID) is cached in a request
        // and a subsequent request with filters - id,userID,storage is made, This will return a folder without
        // the storage attribute and will lead to errors.
        $folder = $this->repository->find($folderID);

        $this->cache->put($key, $folder, now()->addHour());

        return $this->filter($folder, $attributes);
    }

    private function filter(Folder $folder, FolderAttributes $attributes): Folder
    {
        if ($attributes->isEmpty()) {
            return $folder;
        }

        return (new Builder())
            ->when($attributes->has('id'), fn (Builder $b) => $b->setID($folder->folderID->toInt()))
            ->when($attributes->has('user_id'), fn (Builder $b) => $b->setOwnerID($folder->ownerID))
            ->when($attributes->has('bookmarks_count'), fn (Builder $b) => $b->setBookmarksCount($folder->storage->total))
            ->when($attributes->has('is_public'), fn (Builder $b) => $b->setisPublic($folder->isPublic))
            ->when($attributes->has('name'), fn (Builder $b) => $b->setName($folder->name->value))
            ->when($attributes->has('description'), fn (Builder $b) => $b->setDescription($folder->description->value))
            ->when($attributes->has('tags'), fn (Builder $b) => $b->setTags($folder->tags))
            ->build();
    }

    public function forget(ResourceID $folderID): void
    {
        $this->cache->forget($this->key($folderID));
    }

    private function key(ResourceID $folderID): string
    {
        return 'flds::' . $folderID->toInt();
    }
}
