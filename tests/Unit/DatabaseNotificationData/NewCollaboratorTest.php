<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use App\Notifications\NewCollaboratorNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class NewCollaboratorTest extends TestCase
{
    //migrate the latest jsonSchema
    use LazilyRefreshDatabase, Assert {
        canBeSavedToDB as assertCanBeSavedToDB;
    }

    public function testValid(): void
    {
        $this->assertTrue($this->canBeSavedToDB($this->notificationPayload()));
    }

    public function testWillThrowExceptionWhenAllAttributesAreNotPresent(): void
    {
        foreach ($interacted = ['N-type', 'added_by_collaborator', 'added_to_folder', 'version', 'new_collaborator_id'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            unset($data[$property]);

            $this->assertFalse($this->canBeSavedToDB($data), "Failed asserting that [$property] failed validation when not included in payload");
        }

        $this->assertEquals(
            array_values(Arr::sort($interacted)),
            collect($this->notificationPayload())->keys()->sort()->values()->all()
        );
    }

    private function assertKeyIsDefinedInPayload(string $key): void
    {
        if (!Arr::has($this->notificationPayload(), $key)) {
            throw new \ErrorException("Undefined array key $key");
        }
    }

    public function testWillThrowExceptionWhenVersionIsInValid(): void
    {
        $data = $this->notificationPayload();
        $this->assertKeyIsDefinedInPayload('version');
        $data['version'] = 'foo';

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenPayloadHasAdditionalAttributes(): void
    {
        $data = $this->notificationPayload();
        $data['anotherVal'] = 'foo';

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenTypeAttributeIsInvalid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload('N-type');

        $data['N-type'] = 'foo';

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenIdIsNotAnInteger(): void
    {
        foreach (['new_collaborator_id', 'added_by_collaborator', 'added_to_folder'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = '34';

            $this->assertFalse($this->canBeSavedToDB($data), "Failed asserting that [$property] failed validation when not an integer");
        }
    }

    private function notificationPayload(): array
    {
        return (new NewCollaboratorNotification(4, 3, 2))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::NEW_COLLABORATOR);
    }
}
