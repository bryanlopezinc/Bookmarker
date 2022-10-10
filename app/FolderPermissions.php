<?php

declare(strict_types=1);

namespace App;

use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Models\FolderPermission;

/**
 * Permission a user has to a folder resource.
 */
final class FolderPermissions
{
    private const VIEW_BOOKMARKS = 'view_bookmarks';

    /**
     * @param array<string> $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        $valid = array_values((new \ReflectionClass($this))->getConstants());

        foreach ($permissions as $permission) {
            if (!in_array($permission, $valid, true)) {
                throw new \Exception('Invalid permission type ' . $permission);
            }
        }
    }

    /**
     * @param array<string> $result
     */
    public static function fromFolderPermissionsQuery(array $result): self
    {
        return static::translate($result, [
            FolderPermission::VIEW_BOOKMARKS => self::VIEW_BOOKMARKS
        ]);
    }

    public static function fromRequest(Request $request): self
    {
        return static::translate($request->input('permissions'), [
            'viewBookmarks' => self::VIEW_BOOKMARKS
        ]);
    }

    /**
     * @param array<string> $data
     * @param array<string,string> $translation
     */
    private static function translate(array $data, array $translation): self
    {
        $permissions = [];

        if (empty($data)) {
            return new self($permissions);
        }

        foreach ($data as $permission) {
            $permissions[] = $translation[$permission];
        }

        return new self($permissions);
    }

    public function serialize(): array
    {
        $serializable = [];

        $translation = [
            self::VIEW_BOOKMARKS => 'V_B'
        ];

        foreach ($this->permissions as $permission) {
            $serializable[] = $translation[$permission];
        }

        return $serializable;
    }

    public function hasAnyPermission(): bool
    {
        return count($this->permissions) > 0;
    }
}
