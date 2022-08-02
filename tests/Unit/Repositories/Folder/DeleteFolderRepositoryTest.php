<?php

namespace Tests\Unit\Repositories\Folder;

use App\Collections\TagsCollection;
use App\Models\Taggable;
use App\Repositories\Folder\DeleteFolderRepository;
use App\Repositories\TagRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class DeleteFolderRepositoryTest extends TestCase
{
    private DeleteFolderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DeleteFolderRepository::class);
    }

    public function testWillDeleteOnlyFolderTags(): void
    {
        $user = UserFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create(['user_id' => $user->id]);
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagsCollection::make(TagFactory::new()->count(5)->make());

        (new TagRepository)->attach($tags, $bookmark);
        (new TagRepository)->attach($tags, $folder);

        $this->assertDatabaseHas(Taggable::class,$folderTagsData = [
            'taggable_id' => $folder->id,
            'taggable_type' => Taggable::FOLDER_TYPE,
        ]);

        $this->repository->delete(new ResourceID($folder->id));

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $bookmark->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
        ]);

        $this->assertDatabaseMissing(Taggable::class, $folderTagsData);
    }
}
