<?php

namespace Tests\Unit\Notifications;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\Notifications\FolderUpdatedNotification as Notification;
use App\ValueObjects\UserID;
use Database\Factories\UserFactory;
use Tests\TestCase;
use Illuminate\Testing\Assert as PHPUnit;

class FolderUpdatedNotificationTest extends TestCase
{
    public function testWhenOnlyNameWasUpdated(): void
    {
        $original = (new FolderBuilder)
            ->setID(30)
            ->setName('foo')
            ->setDescription($description = 'bar')
            ->setTags([])
            ->build();

        $updated = (new FolderBuilder)
            ->setID(30)
            ->setName('baz')
            ->setDescription($description)
            ->setTags([])
            ->build();

        $notification = new Notification($original, $updated, new UserID(33));

        PHPUnit::assertArraySubset([
            'folder_updated' => 30,
            'updated_by' => 33,
            'changes' => [
                'name' => [
                    'from' => 'foo',
                    'to' => 'baz'
                ]
            ]
        ], $notification->toDatabase(''));
    }

    public function testWhenOnlyDescriptionWasUpdated(): void
    {
        $original = (new FolderBuilder)
            ->setID(30)
            ->setName($name = 'foo')
            ->setDescription('collection of !!')
            ->setTags([])
            ->build();

        $updated = (new FolderBuilder)
            ->setID(30)
            ->setName($name)
            ->setDescription('new collection')
            ->setTags([])
            ->build();

        $notification = new Notification($original, $updated, new UserID(33));

        PHPUnit::assertArraySubset([
            'folder_updated' => 30,
            'updated_by' => 33,
            'changes' => [
                'description' => [
                    'from' => 'collection of !!',
                    'to' => 'new collection'
                ]
            ]
        ], $notification->toDatabase(''));
    }

    public function testWhenTagsWereAddedToAFolderWithoutTags(): void
    {
        $original = (new FolderBuilder)
            ->setID(30)
            ->setName($name = 'foo')
            ->setDescription($description = 'collection of !!')
            ->setTags([])
            ->build();

        $updated = (new FolderBuilder)
            ->setID(30)
            ->setName($name)
            ->setDescription($description)
            ->setTags(['foo', 'bar'])
            ->build();

        $notification = new Notification($original, $updated, new UserID(33));

        PHPUnit::assertArraySubset([
            'folder_updated' => 30,
            'updated_by' => 33,
            'changes' => [
                'tags' => [
                    'from' => '',
                    'to' => 'foo,bar'
                ]
            ]
        ], $notification->toDatabase(''));
    }

    public function testWhenTagsWereAddedToAFolderWithTags(): void
    {
        $original = (new FolderBuilder)
            ->setID(30)
            ->setName($name = 'foo')
            ->setDescription($description = 'collection of !!')
            ->setTags(['foobar'])
            ->build();

        $updated = (new FolderBuilder)
            ->setID(30)
            ->setName($name)
            ->setDescription($description)
            ->setTags(['foo', 'bar'])
            ->build();

        $notification = new Notification($original, $updated, new UserID(33));

        PHPUnit::assertArraySubset([
            'folder_updated' => 30,
            'updated_by' => 33,
            'changes' => [
                'tags' => [
                    'from' => 'foobar',
                    'to' => 'foobar,foo,bar'
                ]
            ]
        ], $notification->toDatabase(''));
    }

    public function testFoldersMustContainChanges(): void
    {
        $this->expectExceptionCode(902);

        $original = (new FolderBuilder)
            ->setID(30)
            ->setName('foo')
            ->setDescription('stop using foo in your tests !!!')
            ->setTags(['foobar'])
            ->build();

        (new Notification($original, $original, new UserID(33)))->toDatabase(UserFactory::new()->make());
    }
}
