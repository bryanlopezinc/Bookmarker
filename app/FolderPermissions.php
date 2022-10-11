<?php

declare(strict_types=1);

namespace App;

use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Models\FolderPermission as Model;

/**
 * Permission a user has to a folder resource.
 */
final class FolderPermissions
{
    /**
     * @param array<string> $permissions
     */
    public function __construct(public readonly array $permissions)
    {
        $valid = [
            Model::VIEW_BOOKMARKS
        ];

        foreach ($permissions as $permission) {
            if (!in_array($permission, $valid, true)) {
                throw new \Exception('Invalid permission type ' . $permission);
            }
        }
    }

    public static function fromRequest(Request $request): self
    {
        return static::translate($request->input('permissions'), [
            'viewBookmarks' => Model::VIEW_BOOKMARKS
        ]);
    }

    /**
     * Create a new instance from an unserialized payload.
     */
    public static function fromUnSerialized(array $unserialized): self
    {
        return static::translate($unserialized, [
            'V_B' => Model::VIEW_BOOKMARKS
        ]);
    }

    /**
     * @param array<string> $data
     * @param array<string,string> $translation
     */
    private static function translate(array $data, array $translation): self
    {
        $permissions = [];

        foreach ($data as $permission) {
            $permissions[] = $translation[$permission];
        }

        return new self($permissions);
    }

    /**
     * @return array<string>
     */
    public function serialize(): array
    {
        $serializable = [];

        $translation = [
            Model::VIEW_BOOKMARKS => 'V_B'
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

    public function canViewBookmarks(): bool
    {
        return $this->hasPermissionTo(Model::VIEW_BOOKMARKS);
    }

    private function hasPermissionTo(string $action): bool
    {
        return in_array($action, $this->permissions, true);
    }
}
