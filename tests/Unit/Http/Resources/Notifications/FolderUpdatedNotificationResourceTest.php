<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Notifications;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Builders\UserBuilder;
use Tests\TestCase;
use App\Http\Resources\Notifications\FolderUpdatedNotificationResource;
use Illuminate\Testing\TestResponse;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Notifications\DatabaseNotification;

class FolderUpdatedNotificationResourceTest extends TestCase
{
    public function testWhenFolderHasBeenDeleted(): void
    {
        $user = UserBuilder::new()->id(44)->firstName('foo')->lastName('baz')->build();
        $notification = new DatabaseNotification([
            'data' => [
                'changes' => [],
                'updated_by' => 33,
                'folder_id' => 40
            ]
        ]);

        $repository = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->getMock();
        $repository->expects($this->once())->method('findFolderByID')->willReturn(null);
        $repository->expects($this->once())->method('findUserByID')->willReturn($user);

        $response = (new FolderUpdatedNotificationResource($notification, $repository))->toResponse(request());

        (new TestResponse($response))
            ->assertJsonCount(5, 'data.attributes')
            ->assertJsonPath('data.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.attributes.folder');
    }

    public function testWhenCollaboratorHasDeletedAccount(): void
    {
        $folder = (new FolderBuilder)->setID(344)->setName('foo')->build();
        $notification = new DatabaseNotification([
            'data' => [
                'changes' => [],
                'updated_by' => 33,
                'folder_id' => 40
            ]
        ]);

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
