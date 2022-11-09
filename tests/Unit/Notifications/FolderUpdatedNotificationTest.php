<?php

namespace Tests\Unit\Notifications;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\Notifications\FolderUpdatedNotification;
use App\ValueObjects\UserID;
use Tests\TestCase;

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

        $notification = new FolderUpdatedNotification($original, $updated, new UserID(33));

        $this->assertEquals($notification->toDatabase(''), [
            'folder_id' => 30,
            'updated_by' => 33,
            'changes' => [
                'name' => [
                    'from' => 'foo',
                    'to' => 'baz'
                ]
            ]
        ]);
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

        $notification = new FolderUpdatedNotification($original, $updated, new UserID(33));

        $this->assertEquals($notification->toDatabase(''), [
            'folder_id' => 30,
            'updated_by' => 33,
            'changes' => [
                'description' => [
                    'from' => 'collection of !!',
                    'to' => 'new collection'
                ]
            ]
        ]);
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

        $notification = new FolderUpdatedNotification($original, $updated, new UserID(33));

        $this->assertEquals($notification->toDatabase(''), [
            'folder_id' => 30,
            'updated_by' => 33,
            'changes' => [
                'tags' => [
                    'from' => '',
                    'to' => 'foo,bar'
                ]
            ]
        ]);
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

        $notification = new FolderUpdatedNotification($original, $updated, new UserID(33));

        $this->assertEquals($notification->toDatabase(''), [
            'folder_id' => 30,
            'updated_by' => 33,
            'changes' => [
                'tags' => [
                    'from' => 'foobar',
                    'to' => 'foobar,foo,bar'
                ]
            ]
        ]);
    }
}
