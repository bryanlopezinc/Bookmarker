<?php

declare(strict_types=1);

namespace App;

use App\Enums\Permission;
use App\Models\FolderPermission;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Permission a user has to a folder resource.
 */
final class UAC implements Countable, Arrayable
{
    /**
     * @var Collection<string>
     */
    private readonly Collection $permissions;

    public function __construct(array|Permission|string|FolderPermission $permissions)
    {
        $this->permissions = $this->resolvePermissions(Arr::wrap($permissions));

        $this->validate();
    }

    private function resolvePermissions(array $permissions): Collection
    {
        return collect($permissions)->map(function (string|Permission|FolderPermission $permission) {
            if ($permission instanceof FolderPermission) {
                $permission = $permission->name;
            }

            if ($permission instanceof Permission) {
                $permission = $permission->value;
            }

            return Permission::from($permission)->value;
        });
    }

    private function validate(): void
    {
        $isUnique = $this->permissions->unique()->count() === $this->permissions->count();

        if (!$isUnique) {
            throw new \Exception('Permissions contains duplicate values', 1_601);
        }
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->permissions->all();
    }

    public function toCollection(): Collection
    {
        return $this->permissions;
    }

    public static function fromRequest(Request $request, string $key): UAC
    {
        $permissions = $request->input($key, []);

        if (in_array('*', $permissions, true)) {
            return self::all();
        }

        $permissions = collect($permissions)->map(function (string $permission) {
            return match ($permission) {
                'addBookmarks'    => Permission::ADD_BOOKMARKS,
                'removeBookmarks' => Permission::DELETE_BOOKMARKS,
                'inviteUser'      => Permission::INVITE_USER,
                'updateFolder'    => Permission::UPDATE_FOLDER
            };
        });

        return new UAC($permissions->all());
    }

    /**
     * @return array<string>
     */
    public function toJsonResponse(): array
    {
        $permissions = $this->permissions
            ->values()
            ->map(function (string $permission) {
                return match ($permission) {
                    Permission::ADD_BOOKMARKS->value     => 'addBookmarks',
                    Permission::DELETE_BOOKMARKS->value  => 'removeBookmarks',
                    Permission::INVITE_USER->value       => 'inviteUsers',
                    Permission::UPDATE_FOLDER->value     => 'updateFolder',
                };
            });

        return $permissions->all();
    }

    public static function all(): UAC
    {
        return new UAC(Permission::cases());
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->permissions->count();
    }

    public function hasAllPermissions(): bool
    {
        return $this->count() === count(self::all());
    }

    /**
     * @return array<string>
     */
    public function serialize(): array
    {
        return $this->permissions->all();
    }

    public function isEmpty(): bool
    {
        return $this->permissions->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->permissions->isNotEmpty();
    }

    public function containsAll(UAC $uac): bool
    {
        if ($uac->isEmpty()) {
            return false;
        }

        foreach ($uac->permissions as $permission) {
            if ($this->permissions->doesntContain($permission)) {
                return false;
            }
        }

        return true;
    }

    public function containsAny(UAC $uac): bool
    {
        foreach ($uac->permissions as $permission) {
            if ($this->permissions->contains($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAddBookmarks(): bool
    {
        return $this->permissions->contains(Permission::ADD_BOOKMARKS->value);
    }

    public function canRemoveBookmarks(): bool
    {
        return $this->permissions->contains(Permission::DELETE_BOOKMARKS->value);
    }

    public function canInviteUser(): bool
    {
        return $this->permissions->contains(Permission::INVITE_USER->value);
    }

    public function canUpdateFolder(): bool
    {
        return $this->permissions->contains(Permission::UPDATE_FOLDER->value);
    }
}
