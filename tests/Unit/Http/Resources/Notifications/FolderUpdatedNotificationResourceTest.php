<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Notifications;

use App\DataTransferObjects\Builders\DatabaseNotificationBuilder as Builder;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Builders\UserBuilder;
use Tests\TestCase;
use App\Http\Resources\Notifications\FolderUpdatedNotificationResource;
use App\Notifications\FolderUpdatedNotification;
use Illuminate\Testing\TestResponse;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use App\ValueObjects\UserID;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;

class FolderUpdatedNotificationResourceTest extends TestCase
{
    use WithFaker;

    public function testWhenFolderHasBeenDeleted(): void
    {
        $user = UserBuilder::new()->id(44)->firstName('foo')->lastName('baz')->build();

        $notification = Builder::new()
            ->data($this->getData())
            ->id($this->faker->uuid)
            ->build();

        $repository = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->getMock();
        $repository->expects($this->once())->method('findFolderByID')->willReturn(null);
        $repository->expects($this->once())->method('findUserByID')->willReturn($user);

        $response = (new FolderUpdatedNotificationResource($notification, $repository))->toResponse(request());

        (new TestResponse($response))
            ->assertJsonCount(5, 'data.attributes')
            ->assertJsonPath('data.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.attributes.folder');
    }

    private function getData(): array
    {
        return (new FolderUpdatedNotification(
            FolderBuilder::fromModel(FolderFactory::new()->make())->setTags([])->setID(3)->build(),
            FolderBuilder::fromModel(FolderFactory::new()->make())->setTags([])->setID(3)->build(),
            new UserID(33)
        ))->toDatabase('');
    }

    public function testWhenCollaboratorHasDeletedAccount(): void
    {
        $folder = (new FolderBuilder)->setID(344)->setName('foo')->build();
        $notification = Builder::new()
            ->data($this->getData())
            ->id($this->faker->uuid)
            ->build();

        $repository = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->getMock();
        $repository->expects($this->once())->method('findFolderByID')->willReturn($folder);
        $repository->expects($this->once())->method('findUserByID')->willReturn(null);

        $response = (new FolderUpdatedNotificationResource($notification, $repository))->toResponse(request());

        (new TestResponse($response))
            ->assertJsonCount(5, 'data.attributes')
            ->assertJsonPath('data.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.attributes.collaborator');
    }
}
