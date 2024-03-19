<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Permission;
use App\UAC;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UACTest extends TestCase
{
    public function testWillThrowExceptionWhenPermissionsContainsDuplicateValues(): void
    {
        $this->expectExceptionCode(1601);

        new UAC([Permission::ADD_BOOKMARKS, Permission::ADD_BOOKMARKS]);
    }

    public function testHasAllMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER]);
        $this->assertFalse($uac->hasAll(new UAC([Permission::INVITE_USER, Permission::ADD_BOOKMARKS])));
        $this->assertTrue($uac->hasAll(new UAC([Permission::UPDATE_FOLDER])));
        $this->assertFalse($uac->hasAll(new UAC([])));

        $uac = new UAC([Permission::UPDATE_FOLDER, Permission::INVITE_USER]);
        $this->assertTrue($uac->hasAll(new UAC([Permission::UPDATE_FOLDER])));

        $this->assertFalse((new UAC([]))->hasAll(new UAC([Permission::UPDATE_FOLDER])));
    }

    public function testHasAnyMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER]);
        $this->assertFalse($uac->hasAny(new UAC([Permission::INVITE_USER, Permission::ADD_BOOKMARKS])));
        $this->assertFalse($uac->hasAny(new UAC([])));
        $this->assertTrue($uac->hasAny(new UAC([Permission::UPDATE_FOLDER, Permission::ADD_BOOKMARKS])));
        $this->assertTrue($uac->hasAny(new UAC([Permission::UPDATE_FOLDER])));

        $this->assertFalse((new UAC([]))->hasAny(new UAC([Permission::UPDATE_FOLDER])));
        $this->assertFalse((new UAC([Permission::UPDATE_FOLDER]))->hasAny(new UAC([])));
    }

    public function testCanAddBookmarksMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER]);
        $this->assertFalse($uac->canAddBookmarks());

        $this->assertFalse((new UAC([]))->canAddBookmarks());
        $this->assertTrue((new UAC([Permission::UPDATE_FOLDER, Permission::ADD_BOOKMARKS]))->canAddBookmarks());
    }

    public function testCanRemoveBookmarksMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER]);
        $this->assertFalse($uac->canRemoveBookmarks());

        $this->assertFalse((new UAC([]))->canRemoveBookmarks());
        $this->assertTrue((new UAC([Permission::DELETE_BOOKMARKS, Permission::ADD_BOOKMARKS]))->canRemoveBookmarks());
    }

    public function testCanInviteUserMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER]);
        $this->assertFalse($uac->canInviteUser());

        $this->assertFalse((new UAC([]))->canInviteUser());
        $this->assertTrue((new UAC([Permission::DELETE_BOOKMARKS, Permission::INVITE_USER]))->canInviteUser());
    }

    public function testIsEmptyMethod(): void
    {
        $uac = new UAC([]);
        $this->assertTrue($uac->isEmpty());

        $uac = new UAC([Permission::ADD_BOOKMARKS]);
        $this->assertFalse($uac->isEmpty());
    }

    public function testIsNotEmptyMethod(): void
    {
        $uac = new UAC([]);
        $this->assertFalse($uac->isNotEmpty());

        $uac = new UAC([Permission::ADD_BOOKMARKS]);
        $this->assertTrue($uac->isNotEmpty());
    }

    #[Test]
    public function fromRequestMethod(): void
    {
        $uac = UAC::fromRequest(new Request(['permissions' => ['*']]), 'permissions');
        $this->assertEquals(UAC::all(), $uac);

        $uac = UAC::fromRequest(new Request(['permissions' => ['addBookmarks', 'removeBookmarks']]), 'permissions');
        $this->assertEquals(new UAC([Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]), $uac);
    }

    #[Test]
    public function toExternalIdentifiersMethod(): void
    {
        $uac = new UAC([]);
        $this->assertEquals([], $uac->toExternalIdentifiers());
    }

    #[Test]
    public function canCreateFromString(): void
    {
        $this->expectNotToPerformAssertions();

        new UAC([Permission::UPDATE_FOLDER->value]);
    }
}
