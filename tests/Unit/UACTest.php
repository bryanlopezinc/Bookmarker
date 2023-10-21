<?php

namespace Tests\Unit;

use App\UAC;
use Tests\TestCase;
use App\Models\FolderPermission as Model;

class UACTest extends TestCase
{
    public function testWillThrowExceptionWhenPermissionsAreInvalid(): void
    {
        $this->expectExceptionCode(1600);

        new UAC(['foo']);
    }

    public function testWillThrowExceptionWhenPermissionsContainsDuplicateValues(): void
    {
        $this->expectExceptionCode(1601);

        new UAC([Model::VIEW_BOOKMARKS, Model::VIEW_BOOKMARKS]);
    }

    public function testContainsAllMethod(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAll(new UAC([Model::INVITE, Model::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAll(new UAC([Model::VIEW_BOOKMARKS])));
        $this->assertFalse($uac->containsAll(new UAC([])));

        $uac = new UAC([Model::VIEW_BOOKMARKS, Model::INVITE]);
        $this->assertTrue($uac->containsAll(new UAC([Model::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAll(new UAC([Model::VIEW_BOOKMARKS])));
    }

    public function testContainsAnyMethod(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAny(new UAC([Model::INVITE, Model::ADD_BOOKMARKS])));
        $this->assertFalse($uac->containsAny(new UAC([])));
        $this->assertTrue($uac->containsAny(new UAC([Model::VIEW_BOOKMARKS, Model::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAny(new UAC([Model::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAny(new UAC([Model::VIEW_BOOKMARKS])));
        $this->assertFalse((new UAC([Model::VIEW_BOOKMARKS]))->containsAny(new UAC([])));
    }

    public function testCanAddBookmarksMethod(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canAddBookmarks());

        $this->assertFalse((new UAC([]))->canAddBookmarks());
        $this->assertTrue((new UAC([Model::VIEW_BOOKMARKS, Model::ADD_BOOKMARKS]))->canAddBookmarks());
    }

    public function testCanRemoveBookmarksMethod(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canRemoveBookmarks());

        $this->assertFalse((new UAC([]))->canRemoveBookmarks());
        $this->assertTrue((new UAC([Model::DELETE_BOOKMARKS, Model::ADD_BOOKMARKS]))->canRemoveBookmarks());
    }

    public function testCanInviteUserMethod(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canInviteUser());

        $this->assertFalse((new UAC([]))->canInviteUser());
        $this->assertTrue((new UAC([Model::DELETE_BOOKMARKS, Model::INVITE]))->canInviteUser());
    }

    public function testIsEmptyMethod(): void
    {
        $uac = new UAC([]);
        $this->assertTrue($uac->isEmpty());

        $uac = new UAC([Model::ADD_BOOKMARKS]);
        $this->assertFalse($uac->isEmpty());
    }

    public function testIsNotEmptyMethod(): void
    {
        $uac = new UAC([]);
        $this->assertFalse($uac->isNotEmpty());

        $uac = new UAC([Model::ADD_BOOKMARKS]);
        $this->assertTrue($uac->isNotEmpty());
    }
}
