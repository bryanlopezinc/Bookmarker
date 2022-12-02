<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Collections\ResourceIDsCollection;
use App\Enums\NotificationType;
use App\Notifications\BookmarksAddedToFolderNotification;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class BookmarksAddedToFolderTest extends TestCase
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

    public function testAllAttributesMustBePresent(): void
    {
        foreach ($interacted = ['N-type', 'bookmarks_added_to_folder', 'added_to_folder', 'added_by', 'version'] as $attribute) {
            $data = $this->notificationPayload();
            $message = "Failed asserting that [$attribute] failed validation when not included in payload";

            $this->assertKeyIsDefinedInPayload($attribute);
            unset($data[$attribute]);

            $this->assertFalse($this->isValid($data), $message);
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

    public function testCannotHaveAdditionalAttributes(): void
    {
        $data = $this->notificationPayload();
        $data['anotherVal'] = 'foo';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_n_Type_Attribute_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload('N-type');

        $data['N-type'] = 'foo';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_id_types_must_be_an_integer(): void
    {
        foreach (['added_to_folder', 'added_by'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            $data[$attribute] = '34';

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$attribute] failed validation when not an integer");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_id_types_must_be_greater_than_one(): void
    {
        foreach (['added_to_folder', 'added_by'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            $data[$attribute] = -1;

            $this->assertFalse($this->isValid($data), $message = "Failed asserting that [$attribute] failed validation when less than one");
            $this->assertFalse($this->canBeSavedToDB($data), $message);
        }
    }

    public function test_bookmarks_must_be_unique(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = [10, 10, 10];

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_bookmarks_cannot_be_empty(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = [];

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_bookmarks_cannot_be_more_than_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = range(1, 51);

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_bookmarks_can_be_equal_to_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = range(1, 50);

        $this->assertTrue($this->isValid($data));
        $this->assertTrue($this->canBeSavedToDB($data));
    }

    public function test_bookmark_ids_must_be_integers(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = ['foo', 'bar'];

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_bookmark_ids_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = [-1, 2, 0];

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function test_bookmark_must_be_an_array(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = 'bar';

        $this->assertFalse($this->isValid($data));
        $this->assertFalse($this->canBeSavedToDB($data));
    }

    private function notificationPayload(): array
    {
        return (new BookmarksAddedToFolderNotification(
            ResourceIDsCollection::fromNativeTypes([10, 20, 30]),
            new ResourceID(20),
            new UserID(33)
        ))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::BOOKMARKS_ADDED_TO_FOLDER);
    }
}
