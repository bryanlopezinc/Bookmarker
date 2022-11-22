<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Collections\ResourceIDsCollection;
use App\Enums\NotificationType;
use App\Exceptions\InvalidJsonException;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use App\ValueObjects\DatabaseNotificationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Tests\TestCase;

class BookmarksRemovedFromFolderTest extends TestCase
{
    //migrate the latest jsonSchema
    use LazilyRefreshDatabase;

    public function testValid(): void
    {
        $this->assertTrue($this->isValid($this->notificationPayload()));
    }

    public function testAllPropertiesMustBePresent(): void
    {
        foreach ($interacted = ['N-type', 'bookmarks_removed', 'removed_from_folder', 'removed_by', 'version'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            unset($data[$attribute]);

            $this->assertFalse($this->isValid($data), "Failed asserting that [$attribute] failed validation when not included in payload");
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

    public function testCannotHaveAdditionalAttributes(): void
    {
        $data = $this->notificationPayload();
        $data['anotherVal'] = 'foo';

        $this->assertFalse($this->isValid($data));
    }

    public function test_n_Type_Attribute_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload('N-type');

        $data['N-type'] = 'foo';

        $this->assertFalse($this->isValid($data));
    }

    public function test_id_types_must_be_an_integer(): void
    {
        foreach (['removed_from_folder', 'removed_by'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            $data[$attribute] = '34';

            $this->assertFalse($this->isValid($data), "Failed asserting that [$attribute] failed validation when not an integer");
        }
    }

    public function test_id_types_must_be_greater_than_one(): void
    {
        foreach (['removed_from_folder', 'removed_by'] as $attribute) {
            $data = $this->notificationPayload();
            $this->assertKeyIsDefinedInPayload($attribute);
            $data[$attribute] = -1;

            $this->assertFalse($this->isValid($data), "Failed asserting that [$attribute] failed validation when less than one");
        }
    }

    public function test_bookmarks_must_be_unique(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = [10, 10, 10];
        $this->assertFalse($this->isValid($data));
    }

    public function test_bookmarks_cannot_be_empty(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = [];
        $this->assertFalse($this->isValid($data));
    }

    public function test_bookmarks_cannot_be_more_than_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = range(1, 51);
        $this->assertFalse($this->isValid($data));
    }

    public function test_bookmarks_can_be_equal_to_50(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = range(1, 50);
        $this->assertTrue($this->isValid($data));
    }

    public function test_bookmark_ids_must_be_integers(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = ['foo', 'bar'];
        $this->assertFalse($this->isValid($data));
    }

    public function test_bookmark_ids_must_be_valid(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = [-1, 2, 0];
        $this->assertFalse($this->isValid($data));
    }

    public function test_bookmark_must_be_an_array(): void
    {
        $data = $this->notificationPayload();

        $this->assertKeyIsDefinedInPayload($key = 'bookmarks_removed');

        $data[$key] = 'bar';
        $this->assertFalse($this->isValid($data));
    }

    private function notificationPayload(): array
    {
        return (new BookmarksRemovedFromFolderNotification(
            ResourceIDsCollection::fromNativeTypes([10, 20, 30]),
            new ResourceID(20),
            new UserID(33)
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
                'type' => NotificationType::BOOKMARKS_REMOVED_FROM_FOLDER->value,
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
