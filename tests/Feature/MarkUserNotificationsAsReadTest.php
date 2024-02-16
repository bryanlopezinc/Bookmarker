<?php

namespace Tests\Feature;

use App\Notifications\NewCollaboratorNotification;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MarkUserNotificationsAsReadTest extends TestCase
{
    use WithFaker;

    protected function readNotificationsResponse(array $data = []): TestResponse
    {
        if (array_key_exists('ids', $data)) {
            $data['ids'] = implode(',', $data['ids']);
        }

        return $this->patchJson(route('markNotificationsAsRead'), $data);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/notifications/read', 'markNotificationsAsRead');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->readNotificationsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->readNotificationsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => ['The ids field is required.']]);

        $this->readNotificationsResponse(['ids' => ['foo', '47b69e15-2dce-3f70-8703-bf071ca6fbd1']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0' => ['The ids.0 must be a valid UUID.']]);

        $this->readNotificationsResponse(['ids' => Collection::times(16, fn () => $this->faker->uuid)->all()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => ['The ids must not have more than 15 items.']]);

        $this->readNotificationsResponse(['ids' => ['47b69e15-2dce-3f70-8703-bf071ca6fbd1', '47b69e15-2dce-3f70-8703-bf071ca6fbd1']])
            ->assertJsonValidationErrors([
                "ids.0" => ["The ids.0 field has a duplicate value."],
                "ids.1" => ["The ids.1 field has a duplicate value."]
            ]);
    }

    public function testMarkNotificationsAsRead(): void
    {
        $user = UserFactory::new()->create();

        $user->notify(
            new NewCollaboratorNotification(
                UserFactory::new()->create(),
                FolderFactory::new()->create(),
                UserFactory::new()->create(),
            )
        );

        $notificationID = DatabaseNotification::query()->where('notifiable_id', $user->id)->sole(['id'])->id;

        Passport::actingAs($user);
        $this->readNotificationsResponse(['ids' => [$notificationID]])->assertOk();

        $notification = DatabaseNotification::query()->find($notificationID, ['read_at', 'id']);

        $this->assertNotNull($notification->read_at);
    }

    public function testWillReturnNotFOundWhenNotificationsDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();

        $user->notify(
            new NewCollaboratorNotification(
                UserFactory::new()->create(),
                FolderFactory::new()->create(),
                UserFactory::new()->create(),
            )
        );

        $notificationID = DatabaseNotification::query()->where('notifiable_id', $user->id)->sole(['id'])->id;

        Passport::actingAs(UserFactory::new()->create());
        $this->readNotificationsResponse(['ids' => [$notificationID]])
            ->assertNotFound()
            ->assertExactJson(['message' => 'NotificationNotFound']);
    }

    public function testWillReturnOkWhenNotificationAreAlreadyMarkedAsRead(): void
    {
        $user = UserFactory::new()->create();

        $user->notify(
            new NewCollaboratorNotification(
                UserFactory::new()->create(),
                FolderFactory::new()->create(),
                UserFactory::new()->create(),
            )
        );

        $notificationID = DatabaseNotification::select('id')->where('notifiable_id', $user->id)->sole()->id;

        Passport::actingAs($user);
        $this->readNotificationsResponse($data = ['ids' => [$notificationID]])->assertOk();
        $this->readNotificationsResponse($data)->assertOk();
        $this->readNotificationsResponse($data)->assertOk();
    }

    public function testWillReturnNotFoundWhenNotificationsDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->readNotificationsResponse(['ids' => [$this->faker->uuid]])
            ->assertNotFound()
            ->assertExactJson(['message' => 'NotificationNotFound']);
    }
}
