<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use App\Exceptions\InvalidJsonException;
use App\Notifications\NewCollaboratorNotification;
use App\ValueObjects\DatabaseNotificationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Tests\TestCase;

class NewCollaboratorTest extends TestCase
{
    //migrate the latest jsonSchema
    use LazilyRefreshDatabase;

    public function testValid(): void
    {
        $this->assertTrue($this->isValid($this->notificationPayload()));
    }

    public function testAllPropertiesMustBePresent(): void
    {
        foreach ($interacted = ['N-type', 'added_by_collaborator', 'added_to_folder', 'version', 'new_collaborator_id'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            unset($data[$property]);

            $this->assertFalse($this->isValid($data), "Failed asserting that [$property] failed validation when not included in payload");
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
    }

    public function testCannotHaveAdditionalProperties(): void
    {
        $data = $this->notificationPayload();
        $data['anotherVal'] = 'foo';

        $this->assertFalse($this->isValid($data));
    }

    public function test_n_Type_property_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload('N-type');

        $data['N-type'] = 'foo';

        $this->assertFalse($this->isValid($data));
    }

    public function test_id_properties_must_be_an_integer(): void
    {
        foreach (['new_collaborator_id', 'added_by_collaborator', 'added_to_folder'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = '34';

            $this->assertFalse($this->isValid($data), "Failed asserting that [$property] failed validation when not an integer");
        }
    }

    public function test_id_properties_must_be_greater_than_one(): void
    {
        foreach (['new_collaborator_id', 'added_by_collaborator', 'added_to_folder'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = -1;

            $this->assertFalse($this->isValid($data), "Failed asserting that [$property] failed validation when less than one");
        }
    }

    private function notificationPayload(): array
    {
        return (new NewCollaboratorNotification(
            new UserID(rand(1, 1_000_000)),
            new ResourceID(rand(1, 1_000_000)),
            new UserID(rand(1, 1_000_000))
        ))->toDatabase('');
    }

    private function isValid(array $data): bool
    {
        $valid = true;

        try {
            new DatabaseNotificationData($data);
        } catch (InvalidJsonException) {
            $valid = false;
        }

        try {
            $valid = true;

            DatabaseNotification::query()->create([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => NotificationType::NEW_COLLABORATOR->value,
                'notifiable_type' => 'user',
                'notifiable_id' => rand(1, PHP_INT_MAX),
                'data' => $data
            ]);
        } catch (QueryException $e) {
            $this->assertStringContainsString("Check constraint 'validate_notification_data' is violated", $e->getMessage());
            $valid = false;
        }

        return $valid;
    }
}
