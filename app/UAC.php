<?php

declare(strict_types=1);

namespace App;

use App\Models\FolderPermission as Model;
use Illuminate\Http\Request;

/**
 * Permission a user has to a folder resource.
 */
final class UAC
{
    private const VALID = [
        Model::VIEW_BOOKMARKS,
        Model::ADD_BOOKMARKS,
        Model::DELETE_BOOKMARKS,
        Model::INVITE
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
        return static::translate($request->input($key, []), [
            'addBookmarks' => Model::ADD_BOOKMARKS,
            'removeBookmarks' => Model::DELETE_BOOKMARKS,
            'inviteUser' => Model::INVITE
        ]);
    }

    /**
     * Create a new instance from an unserialize payload.
     */
    public static function fromUnSerialized(array $unserialize): self
    {
        return static::translate($unserialize, [
            'A_B' => Model::ADD_BOOKMARKS,
            'D_B' => Model::DELETE_BOOKMARKS,
            'I_U' => Model::INVITE
        ]);
    }

    /**
     * @param array<string> $permissions Accepted values = ['read']
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
            Model::ADD_BOOKMARKS => 'A_B',
            Model::DELETE_BOOKMARKS => 'D_B',
            Model::INVITE => 'I_U'
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

    public function containsAll(UAC $uac): bool
    {
        if (!$uac->hasAnyPermission()) {
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

    private function hasPermissionTo(string $action): bool
    {
        return in_array($action, $this->permissions, true);
    }
}
