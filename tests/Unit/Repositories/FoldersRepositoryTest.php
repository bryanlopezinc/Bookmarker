<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\Models\UserResourcesCount;
use Tests\TestCase;
use App\ValueObjects\UserID;
use App\Repositories\FoldersRepository;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;

class FoldersRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillIncrementFoldersCount(): void
    {
        $folder = (new FolderBuilder())
            ->setCreatedAt(now())
            ->setDescription($this->faker->sentence)
            ->setName($this->faker->word)
            ->setOwnerID($userID = UserFactory::new()->create()->id)
            ->build();

        $repository = new FoldersRepository;

        $repository->create($folder);
        $repository->create($folder);
        $repository->create($folder);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $userID,
            'count' => 3,
            'type' => UserResourcesCount::FOLDERS_TYPE
        ]);
    }
}
