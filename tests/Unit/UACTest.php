<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Permission;
use App\Models\FolderPermission;
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
        $uac = new UAC([Permission::UPDATE_FOLDER_NAME, Permission::REMOVE_USER]);

        $this->assertFalse($uac->hasAll(new UAC([Permission::INVITE_USER, Permission::UPDATE_FOLDER_NAME])));
        $this->assertTrue($uac->hasAll(new UAC([Permission::UPDATE_FOLDER_NAME])));
        $this->assertFalse($uac->hasAll(new UAC([])));
        $this->assertFalse($uac->hasAll());

        $this->assertFalse((new UAC([]))->hasAll(new UAC([Permission::UPDATE_FOLDER_NAME])));
        $this->assertTrue(UAC::all()->hasAll());
    }

    public function testHasMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER_NAME, Permission::DELETE_BOOKMARKS]);

        $this->assertFalse($uac->has(Permission::ADD_BOOKMARKS));
        $this->assertFalse($uac->has(Permission::ADD_BOOKMARKS->value));
        $this->assertFalse($uac->has(new FolderPermission(['name' => Permission::ADD_BOOKMARKS->value])));

        $this->assertTrue($uac->has(Permission::UPDATE_FOLDER_NAME));
        $this->assertTrue($uac->has(Permission::UPDATE_FOLDER_NAME->value));
        $this->assertTrue($uac->has(new FolderPermission(['name' => Permission::UPDATE_FOLDER_NAME->value])));
    }

    public function testHasAnyMethod(): void
    {
        $uac = new UAC([Permission::UPDATE_FOLDER_NAME]);
        $this->assertFalse($uac->hasAny(new UAC([Permission::INVITE_USER, Permission::ADD_BOOKMARKS])));
        $this->assertFalse($uac->hasAny(new UAC([])));
        $this->assertTrue($uac->hasAny(new UAC([Permission::UPDATE_FOLDER_NAME, Permission::ADD_BOOKMARKS])));
        $this->assertTrue($uac->hasAny(new UAC([Permission::UPDATE_FOLDER_NAME])));

        $this->assertFalse((new UAC([]))->hasAny(new UAC([Permission::UPDATE_FOLDER_NAME])));
        $this->assertFalse((new UAC([Permission::UPDATE_FOLDER_NAME]))->hasAny(new UAC([])));
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
    public function exceptMethod(): void
    {
        $uac = new UAC([]);
        $this->assertFalse($uac->isNotEmpty());

        $uac = new UAC([Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);
        $uac = $uac->except(Permission::DELETE_BOOKMARKS);

        $this->assertEquals([Permission::ADD_BOOKMARKS->value], $uac->toArray());
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
    public function validExternalIdentifiersMethod(): void
    {
        $this->assertEquals(count(Permission::cases()), count(UAC::validExternalIdentifiers()));
    }

    #[Test]
    public function canCreateFromString(): void
    {
        $this->expectNotToPerformAssertions();

        new UAC([Permission::UPDATE_FOLDER_NAME->value]);
    }
}
