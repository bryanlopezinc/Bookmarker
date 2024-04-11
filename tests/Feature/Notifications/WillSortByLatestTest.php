<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Database\Factories\UserFactory;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\ImportBookmarksOutcome;
use App\Notifications\BookmarksAddedToFolderNotification;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use App\Notifications\CollaboratorExitNotification;
use App\Importing\Notifications\ImportFailedNotification;
use App\Notifications\FolderNameUpdatedNotification;
use App\Notifications\NewCollaboratorNotification;
use App\Notifications\YouHaveBeenBootedOutNotification;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;

class WillSortByLatestTest extends TestCase
{
    use MakesHttpRequest;

    #[Test]
    public function assert(): void
    {
        [$user, $firstCollaborator, $secondCollaborator, $newCollaborator] = UserFactory::times(4)->create();

        $bookmarks = BookmarkFactory::times(3)->create()->pluck('id');
        $userFolders = FolderFactory::times(3)->for($user)->create();

        $notifications = [
            new BookmarksAddedToFolderNotification($bookmarks->all(), $userFolders[0], $firstCollaborator),
            new BookmarksRemovedFromFolderNotification($bookmarks->all(), $userFolders[1], $secondCollaborator),
            new NewCollaboratorNotification($newCollaborator, $userFolders[2], $secondCollaborator),
            new CollaboratorExitNotification($userFolders[2], $newCollaborator),
            new FolderNameUpdatedNotification($userFolders[1], $firstCollaborator),
            new ImportFailedNotification(fake()->uuid, ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats())),
            new YouHaveBeenBootedOutNotification(FolderFactory::new()->create())
        ];

        foreach ($notifications as $key => $notification) {
            $this->travelTo(now()->addMinutes($key + 1), fn () => $user->notify($notification));
        }

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.*.type', function (array $types) {
                $this->assertEquals($types, [
                    'YouHaveBeenKickedOutNotification',
                    'ImportFailedNotification',
                    'FolderUpdatedNotification',
                    'CollaboratorExitNotification',
                    'CollaboratorAddedToFolderNotification',
                    'BookmarksRemovedFromFolderNotification',
                    'BookmarksAddedToFolderNotification'
                ]);

                return true;
            });
    }
}
