<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use App\Notifications\CollaboratorExitNotification;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class CollaboratorExitTest extends TestCase
{
    //migrate the latest jsonSchema
    use LazilyRefreshDatabase, Assert {
        canBeSavedToDB as assertCanBeSavedToDB;
    }

    public function testValid(): void
    {
        $this->assertTrue($this->isValid($data = $this->notificationPayload()));
        $this->assertTrue($this->canBeSavedToDB($data));
    }

    public function testAllPropertiesMustBePresent(): void
    {
        foreach ($interacted = ['N-type', 'exited_from_folder', 'exited_by', 'version'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            unset($data[$property]);

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when not included in payload");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
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

    public function testVersionMustBeValid(): void
    {
        $data = $this->notificationPayload();
        $this->assertKeyIsDefinedInPayload('version');
        $data['version'] = 'foo';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testCannotHaveAdditionalProperties(): void
    {
        $data = $this->notificationPayload();
        $data['anotherVal'] = 'foo';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_n_Type_property_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload('N-type');

        $data['N-type'] = 'foo';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_id_properties_must_be_an_integer(): void
    {
        foreach (['exited_by', 'exited_from_folder'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = '34';

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when not an integer");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_id_properties_must_be_greater_than_one(): void
    {
        foreach (['exited_from_folder', 'exited_by'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = -1;

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when less than one");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    private function notificationPayload(): array
    {
        return (new CollaboratorExitNotification(
            new ResourceID(rand(1, 1_000_000)),
            new UserID(rand(1, 1_000_000))
        ))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::COLLABORATOR_EXIT);
    }
}
