<?php

namespace Tests\Unit\Repositories\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\Models\UserFoldersCount;
use App\Repositories\Folder\CreateFolderRepository;
use Tests\TestCase;
use App\Repositories\TagRepository;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;

class CreateFolderRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillIncrementFoldersCount(): void
    {
        $folder = (new FolderBuilder())
            ->setCreatedAt(now())
            ->setDescription($this->faker->sentence)
            ->setName($this->faker->word)
            ->setOwnerID($userID = UserFactory::new()->create()->id)
            ->setIsPublic(false)
            ->setTags(new TagsCollection([]))
            ->build();

        $repository = new CreateFolderRepository(new TagRepository);

        $repository->create($folder);
        $repository->create($folder);
        $repository->create($folder);

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $userID,
            'count' => 3,
            'type' => UserFoldersCount::TYPE
        ]);
    }
}
