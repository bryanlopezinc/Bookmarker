<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use App\Notifications\BookmarksAddedToFolderNotification;
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
        $this->assertTrue($this->canBeSavedToDB($this->notificationPayload()));
    }

    public function testWillThrowExceptionWhenAllAttributesAreNotPresent(): void
    {
        foreach ($interacted = ['N-type', 'bookmarks_added_to_folder', 'added_to_folder', 'added_by', 'version'] as $attribute) {
            $data = $this->notificationPayload();
            $message = "Failed asserting that [$attribute] failed validation when not included in payload";

            $this->assertKeyIsDefinedInPayload($attribute);
            unset($data[$attribute]);

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
        foreach (['added_to_folder', 'added_by'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            $data[$attribute] = '34';

            $this->assertFalse($this->canBeSavedToDB($data));
        }
    }

    public function testWillThrowExceptionWhenBookmarksAreNotUnique(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = [10, 10, 10];

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenBookmarksAreEmpty(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = [];

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenBookmarksCountIsGreaterThan_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = range(1, 51);

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillNotThrowExceptionWhenBookmarksCountIsEqualTo_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = range(1, 50);

        $this->assertTrue($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenBookmarksIdsContainsInvalidIntegers(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = ['foo', 'bar'];

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    public function testWillThrowExceptionWhenBookmarksIsNotAnArray(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_added_to_folder');

        $data[$key] = 'bar';

        $this->assertFalse($this->canBeSavedToDB($data));
    }

    private function notificationPayload(): array
    {
        return (new BookmarksAddedToFolderNotification(
            [10, 20, 30],
            20,
            33
        ))->toDatabase('');
    }

    private function canBeSavedToDB(array $data): bool
    {
        return $this->assertCanBeSavedToDB($data, NotificationType::BOOKMARKS_ADDED_TO_FOLDER);
    }
}
