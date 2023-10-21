<?php

declare(strict_types=1);

namespace App;

use App\Models\FolderPermission as Model;
use Countable;
use Illuminate\Http\Request;

/**
 * Permission a user has to a folder resource.
 */
final class UAC implements Countable
{
    private const VALID = [
        Model::VIEW_BOOKMARKS,
        Model::ADD_BOOKMARKS,
        Model::DELETE_BOOKMARKS,
        Model::INVITE,
        Model::UPDATE_FOLDER
    ];

    /**
     * @param array<string> $permissions
     */
    public function __construct(public readonly array $permissions)
    {
        $isUnique = count(array_unique($permissions)) === count($permissions);

        foreach ($permissions as $permission) {
            if (!in_array($permission, self::VALID, true)) {
                throw new \Exception('Invalid permission type ' . $permission, 1_600);
            }
        }

        if (!$isUnique) {
            throw new \Exception('Permissions contains duplicate values : ' . implode(',', $permissions), 1_601);
        }
    }

    public static function fromRequest(Request $request, string $key): self
    {
        if (in_array('*', $request->input($key, []), true)) {
            return new self(self::VALID);
        }

        $permissions = [];

        $translation = [
            'addBookmarks'    => Model::ADD_BOOKMARKS,
            'removeBookmarks' => Model::DELETE_BOOKMARKS,
            'inviteUser'      => Model::INVITE,
            'updateFolder'    => Model::UPDATE_FOLDER
        ];

        foreach ($request->input($key, []) as $permission) {
            $permissions[] = $translation[$permission];
        }

        return new self($permissions);
    }

    /**
     * @return array<string>
     */
    public function toJsonResponse(): array
    {
        $response = [];

        $translation = [
            Model::ADD_BOOKMARKS     => 'addBookmarks',
            Model::DELETE_BOOKMARKS  => 'removeBookmarks',
            Model::INVITE            => 'inviteUsers',
            Model::UPDATE_FOLDER     => 'updateFolder',
        ];

        foreach ($this->permissions as $permission) {
            if ($permission === Model::VIEW_BOOKMARKS) {
                continue;
            }

            $response[] = $translation[$permission];
        }

        return $response;
    }

    public static function all(): self
    {
        return new self(self::VALID);
    }

    public function count(): int
    {
        return count($this->permissions);
    }

    public function hasAllPermissions(): bool
    {
        return count($this) === count((new self(self::VALID)));
    }

    /**
     * Create a new instance from an unserialize payload.
     */
    public static function fromUnSerialized(array $unserialize): self
    {
        return new self($unserialize);
    }

    /**
     * @return array<string>
     */
    public function serialize(): array
    {
        return $this->permissions;
    }

    public function isEmpty(): bool
    {
        return empty($this->permissions);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function containsAll(UAC $uac): bool
    {
        if ($uac->isEmpty()) {
            return false;
        }

        foreach ($uac->permissions as $action) {
            if (!$this->hasPermissionTo($action)) {
                return false;
            }
        }

        return true;
    }

    public function containsAny(UAC $uac): bool
    {
        foreach ($uac->permissions as $action) {
            if ($this->hasPermissionTo($action)) {
                return true;
            }
        }

        return false;
    }

    public function canAddBookmarks(): bool
    {
        return $this->hasPermissionTo(Model::ADD_BOOKMARKS);
    }

    public function canRemoveBookmarks(): bool
    {
        return $this->hasPermissionTo(Model::DELETE_BOOKMARKS);
    }

    public function canInviteUser(): bool
    {
        return $this->hasPermissionTo(Model::INVITE);
    }

    public function canUpdateFolder(): bool
    {
        return $this->hasPermissionTo(Model::UPDATE_FOLDER);
    }

    public function hasOnlyReadPermission(): bool
    {
        return $this->hasPermissionTo(Model::VIEW_BOOKMARKS) && count($this->permissions) === 1;
    }

    private function hasPermissionTo(string $action): bool
    {
        return in_array($action, $this->permissions, true);
    }
}
