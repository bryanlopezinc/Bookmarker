<?php

namespace Tests\Unit;

use App\UAC;
use Tests\TestCase;
use App\Models\FolderPermission as Model;

class UACTest extends TestCase
{
    public function testPermissionsMustBeValid(): void
    {
        $this->expectExceptionCode(1600);

        new UAC(['foo']);
    }

    public function testPermissionsMustBeUnique(): void
    {
        $this->expectExceptionCode(1601);

        new UAC([Model::VIEW_BOOKMARKS, Model::VIEW_BOOKMARKS]);
    }

    public function testContainsAll(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAll(new UAC([Model::INVITE, Model::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAll(new UAC([Model::VIEW_BOOKMARKS])));
        $this->assertFalse($uac->containsAll(new UAC([])));

        $uac = new UAC([Model::VIEW_BOOKMARKS, Model::INVITE]);
        $this->assertTrue($uac->containsAll(new UAC([Model::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAll(new UAC([Model::VIEW_BOOKMARKS])));
    }

    public function testContainsAny(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAny(new UAC([Model::INVITE, Model::ADD_BOOKMARKS])));
        $this->assertFalse($uac->containsAny(new UAC([])));
        $this->assertTrue($uac->containsAny(new UAC([Model::VIEW_BOOKMARKS, Model::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAny(new UAC([Model::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAny(new UAC([Model::VIEW_BOOKMARKS])));
        $this->assertFalse((new UAC([Model::VIEW_BOOKMARKS]))->containsAny(new UAC([])));
    }

    public function testCanAddBookmarks(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canAddBookmarks());

        $this->assertFalse((new UAC([]))->canAddBookmarks());
        $this->assertTrue((new UAC([Model::VIEW_BOOKMARKS, Model::ADD_BOOKMARKS]))->canAddBookmarks());
    }

    public function testCanRemoveBookmarks(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canRemoveBookmarks());

        $this->assertFalse((new UAC([]))->canRemoveBookmarks());
        $this->assertTrue((new UAC([Model::DELETE_BOOKMARKS, Model::ADD_BOOKMARKS]))->canRemoveBookmarks());
    }

    public function testCanInviteUser(): void
    {
        $uac = new UAC([Model::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canInviteUser());

        $this->assertFalse((new UAC([]))->canInviteUser());
        $this->assertTrue((new UAC([Model::DELETE_BOOKMARKS, Model::INVITE]))->canInviteUser());
    }

    public function testIsEmpty(): void
    {
        $uac = new UAC([]);
        $this->assertTrue($uac->isEmpty());

        $uac = new UAC([Model::ADD_BOOKMARKS]);
        $this->assertFalse($uac->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $uac = new UAC([]);
        $this->assertFalse($uac->isNotEmpty());

        $uac = new UAC([Model::ADD_BOOKMARKS]);
        $this->assertTrue($uac->isNotEmpty());
    }
}
