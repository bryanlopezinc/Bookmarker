<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use App\Notifications\FolderUpdatedNotification;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class FolderUpdatedTest extends TestCase
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
        foreach ($interacted = ['N-type', 'updated_by', 'folder_updated', 'changes', 'version'] as $property) {
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
        foreach (['updated_by', 'folder_updated'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = '34';

            $this->assertFalse($this->canBeSavedToDB($data), "Failed asserting that [$property] failed validation when not an integer");
        }
    }

    public function testWillThrowExceptionWhenChangesAttributeHasAdditionalValues(): void
    {
        $data = $this->notificationPayload();
        $this->assertKeyIsDefinedInPayload('changes');
        $data['changes'] = array_merge($data['changes'], ['foo' => 'bar']);

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenChangesAttributesHasAdditionalValues(): void
    {
        foreach (['name', 'description'] as $property) {
            $data = $this->notificationPayload();

            $this->assertKeyIsDefinedInPayload($key = "changes.$property");

            Arr::set($data, $key, array_merge($data['changes'][$property], ['baz' => 'bar']));

            $this->assertFalse($this->canBeSavedToDB($data), "Failed asserting that [$property] failed validation when has more attribute/s");
        }
    }

    public function testWillThrowExceptionWhenChangesAttributeDoesNotIncludeAllValues(): void
    {
        foreach (['from', 'to'] as $attribute) {
            foreach (['name', 'description'] as $property) {
                $data = $this->notificationPayload();
                Arr::forget($data, $key = "changes.$property.$attribute");
                $this->assertKeyIsDefinedInPayload($key);

                $this->assertFalse($this->canBeSavedToDB($data), "Failed asserting that [$property.$attribute] failed validation when not present");
            }
        }
    }

    private function notificationPayload(): array
    {
        $folder = FolderFactory::new()->create();
        $folder->description = 'foo';
        $folder->name = 'bar';

        return (new FolderUpdatedNotification(
            $folder,
            33,
        ))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::FOLDER_UPDATED);
    }
}
