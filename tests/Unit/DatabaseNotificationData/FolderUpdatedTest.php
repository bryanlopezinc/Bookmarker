<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\Enums\NotificationType;
use App\Notifications\FolderUpdatedNotification;
use App\ValueObjects\UserID;
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
        $this->assertTrue($this->isValid($data = $this->notificationPayload()));
        $this->assertTrue($this->canBeSavedToDB($data));
    }

    public function testAllPropertiesMustBePresent(): void
    {
        foreach ($interacted = ['N-type', 'updated_by', 'folder_updated', 'changes', 'version'] as $property) {
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
        foreach (['updated_by', 'folder_updated'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = '34';

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when not an integer");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_id_properties_must_be_greater_than_one(): void
    {
        foreach (['updated_by', 'folder_updated'] as $property) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($property);
            $data[$property] = -1;

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when less than one");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_changes_property_cannot_have_additional_properties(): void
    {
        $data = $this->notificationPayload();
        $this->assertKeyIsDefinedInPayload('changes');
        $data['changes'] = array_merge($data['changes'], ['foo' => 'bar']);

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_changes_properties_cannot_have_additional_properties(): void
    {
        foreach (['name', 'description', 'tags'] as $property) {
            $data = $this->notificationPayload();

            $this->assertKeyIsDefinedInPayload($key = "changes.$property");

            Arr::set($data, $key, array_merge($data['changes'][$property], ['baz' => 'bar']));

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property] failed validation when has more attribute/s");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_all_properties_must_be_present_in_changes_sub_types(): void
    {
        foreach (['from', 'to'] as $attribute) {
            foreach (['name', 'description', 'tags'] as $property) {
                $data = $this->notificationPayload();
                Arr::forget($data, $key = "changes.$property.$attribute");
                $this->assertKeyIsDefinedInPayload($key);

                $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$property.$attribute] failed validation when not present");
                $this->assertFalse($this->canBeSavedToDB($data), $message);
            }
        }
    }

    private function notificationPayload(): array
    {
        $builder = (new FolderBuilder)
            ->setID(30)
            ->setDescription('foo')
            ->setName('bar')
            ->setTags(['foo']);

        return (new FolderUpdatedNotification(
            $builder->build(),
            $builder->setName('baz')->setDescription('fooBar')->setTags(['tags'])->build(),
            new UserID(33)
        ))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::FOLDER_UPDATED);
    }
}
