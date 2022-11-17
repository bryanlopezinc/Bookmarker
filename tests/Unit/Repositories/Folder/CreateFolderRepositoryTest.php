<?php

namespace Tests\Unit\Repositories\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\Models\UserFoldersCount;
use App\Repositories\Folder\CreateFolderRepository;
use Tests\TestCase;
use App\Repositories\TagRepository;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;

class CreateFolderRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillIncrementFoldersCount(): void
    {
        $folder = FolderBuilder::fromModel(FolderFactory::new()->make())
            ->setCreatedAt(now())
            ->setTags(new TagsCollection([]))
            ->build();

        $repository = new CreateFolderRepository(new TagRepository);

        $repository->create($folder);
        $repository->create($folder);
        $repository->create($folder);

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $folder->ownerID->value(),
            'count' => 3,
            'type' => UserFoldersCount::TYPE
        ]);
    }
}
