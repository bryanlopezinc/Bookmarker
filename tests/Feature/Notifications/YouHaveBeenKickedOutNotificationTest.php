<?php

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

        $expectedDateTime = $collaborator->notifications()->sole(['created_at'])->created_at;

        $this->loginUser($collaborator);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'YouHaveBeenKickedOutNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.folder_id', $folder->id)
            ->assertJsonPath('data.0.attributes.message', "You were removed from Tech Problems folder.")
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "folder_exists",
                            'notified_on',
                            "folder_id",
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
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonPath('data.0.attributes.message', "You were removed from {$folder->name->present()} folder.");
    }
}
