<?php

namespace Tests\Feature\Notifications;

use App\Import\ImportStats;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use App\Notifications\ImportFailedNotification;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use PHPUnit\Framework\Attributes\Test;

class ImportFailedNotificationTest extends TestCase
{
    use MakesHttpRequest;
    use WithFaker;

    #[Test]
    public function fetchNotifications(): void
    {
        $user = UserFactory::new()->create();

        $notification = new ImportFailedNotification(
            $importId = $this->faker->uuid,
            ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats())
        );

        $user->notify($notification);

        $expectedDateTime = DatabaseNotification::where('notifiable_id', $user->id)->sole(['created_at'])->created_at;

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'ImportFailedNotification')
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.import_id', $importId)
            ->assertJsonPath('data.0.attributes.reason', 'FailedDueToSystemError')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "import_id",
                            "reason",
                            'notified_on'
                        ]
                    ]
                ]
            ]);
    }
}
