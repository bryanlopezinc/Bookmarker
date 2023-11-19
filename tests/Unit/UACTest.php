<?php

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

        new UAC([Permission::VIEW_BOOKMARKS, Permission::VIEW_BOOKMARKS]);
    }

    public function testContainsAllMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAll(new UAC([Permission::INVITE_USER, Permission::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAll(new UAC([Permission::VIEW_BOOKMARKS])));
        $this->assertFalse($uac->containsAll(new UAC([])));

        $uac = new UAC([Permission::VIEW_BOOKMARKS, Permission::INVITE_USER]);
        $this->assertTrue($uac->containsAll(new UAC([Permission::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAll(new UAC([Permission::VIEW_BOOKMARKS])));
    }

    public function testContainsAnyMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->containsAny(new UAC([Permission::INVITE_USER, Permission::ADD_BOOKMARKS])));
        $this->assertFalse($uac->containsAny(new UAC([])));
        $this->assertTrue($uac->containsAny(new UAC([Permission::VIEW_BOOKMARKS, Permission::ADD_BOOKMARKS])));
        $this->assertTrue($uac->containsAny(new UAC([Permission::VIEW_BOOKMARKS])));

        $this->assertFalse((new UAC([]))->containsAny(new UAC([Permission::VIEW_BOOKMARKS])));
        $this->assertFalse((new UAC([Permission::VIEW_BOOKMARKS]))->containsAny(new UAC([])));
    }

    public function testCanAddBookmarksMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canAddBookmarks());

        $this->assertFalse((new UAC([]))->canAddBookmarks());
        $this->assertTrue((new UAC([Permission::VIEW_BOOKMARKS, Permission::ADD_BOOKMARKS]))->canAddBookmarks());
    }

    public function testCanRemoveBookmarksMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
        $this->assertFalse($uac->canRemoveBookmarks());

        $this->assertFalse((new UAC([]))->canRemoveBookmarks());
        $this->assertTrue((new UAC([Permission::DELETE_BOOKMARKS, Permission::ADD_BOOKMARKS]))->canRemoveBookmarks());
    }

    public function testCanInviteUserMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
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
        $uac = UAC::fromRequest(new Request(['key' => ['*']]), 'key');
        $this->assertEquals(UAC::all(), $uac);
    }

    #[Test]
    public function toJsonResponseMethod(): void
    {
        $uac = new UAC([Permission::VIEW_BOOKMARKS]);
        $this->assertEquals([], $uac->toJsonResponse());
    }

    #[Test]
    public function canCreateFromString(): void
    {
        $this->expectNotToPerformAssertions();

        new UAC([Permission::VIEW_BOOKMARKS->value]);
    }
}
