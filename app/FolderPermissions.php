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
     * @var array<string>
     */
    private const VALID = [
        Model::VIEW_BOOKMARKS,
        Model::ADD_BOOKMARKS,
        Model::DELETE_BOOKMARKS
    ];

    /**
     * @param array<string> $permissions
     */
    public function __construct(public readonly array $permissions)
    {
        foreach ($permissions as $permission) {
            if (!in_array($permission, self::VALID, true)) {
                throw new \Exception('Invalid permission type ' . $permission);
            }
        }
    }

    public static function fromRequest(Request $request): self
    {
        return static::translate($request->input('permissions', []), [
            'addBookmarks' => Model::ADD_BOOKMARKS
        ]);
    }

    /**
     * Create a new instance from an unserialized payload.
     */
    public static function fromUnSerialized(array $unserialized): self
    {
        return static::translate($unserialized, [
            'A_B' => Model::ADD_BOOKMARKS
        ]);
    }

    /**
     * @param array<string> $permissions Accpted values = ['read', 'write']
     */
    public static function fromArray(array $permissions): self
    {
        return static::translate($permissions, [
            'read' => Model::VIEW_BOOKMARKS,
            'write' => Model::ADD_BOOKMARKS
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
            Model::ADD_BOOKMARKS => 'A_B'
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

    public function canAddBookmarksToFolder(): bool
    {
        return $this->hasPermissionTo(Model::ADD_BOOKMARKS);
    }

    public function canRemoveBookmarksFromFolder(): bool
    {
        return $this->hasPermissionTo(Model::DELETE_BOOKMARKS);
    }

    private function hasPermissionTo(string $action): bool
    {
        return in_array($action, $this->permissions, true);
    }
}
