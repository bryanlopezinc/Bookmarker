<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use App\Notifications\YouHaveBeenBootedOutNotification;
use PHPUnit\Framework\Attributes\Test;

class YouHaveBeenKickedOutNotificationTest extends TestCase
{
    use MakesHttpRequest;

    #[Test]
    public function willFetchNotifications(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $notification = new YouHaveBeenBootedOutNotification($folder);

        $folder->update(['name' => 'tech problems']);
        $collaborator->notify($notification);

        $this->loginUser($collaborator);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'YouHaveBeenKickedOutNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonPath('data.0.attributes.message', 'You were removed from Tech Problems folder')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'folder' => ['id', 'exists'],
                            'notified_on',
                            'message',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function willReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $notification = new YouHaveBeenBootedOutNotification($folder);

        $collaborator->notify($notification);

        $folder->delete();

        $this->loginUser($collaborator);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder.exists', false)
            ->assertJsonPath('data.0.attributes.message', "You were removed from {$folder->name->present()} folder");
    }
}
