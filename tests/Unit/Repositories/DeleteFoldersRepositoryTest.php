<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\Models\Taggable;
use App\Repositories\Folder\DeleteFoldersRepository;
use App\Repositories\TagsRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class DeleteFoldersRepositoryTest extends TestCase
{
    private DeleteFoldersRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DeleteFoldersRepository::class);
    }

    public function testWillDeleteOnlyFolderTags(): void
    {
        $user = UserFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create(['user_id' => $user->id]);
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagsCollection::make(TagFactory::new()->count(5)->make());

        (new TagsRepository)->attach($tags, $bookmark);
        (new TagsRepository)->attach($tags, $folder);

        $this->assertDatabaseHas(Taggable::class,$folderTagsData = [
            'taggable_id' => $folder->id,
            'taggable_type' => Taggable::FOLDER_TYPE,
            'tagged_by_id' => $user->id
        ]);

        $this->repository->delete(new ResourceID($folder->id));

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $bookmark->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tagged_by_id' => $user->id
        ]);

        $this->assertDatabaseMissing(Taggable::class, $folderTagsData);
    }
}
