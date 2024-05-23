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
use IteratorAggregate;
use Traversable;
use Exception;

/**
 * Permission a user has to a folder resource.
 */
final class UAC implements Countable, Arrayable, IteratorAggregate
{
    /**
     * @var Collection<string>
     */
    private readonly Collection $permissions;

    public function __construct(array|Permission|string|FolderPermission $permissions)
    {
        $this->permissions = $this->resolvePermissions(Arr::wrap($permissions));

        $this->permissions->duplicates()->whenNotEmpty(function () {
            throw new Exception('Permissions contains duplicate values', 1_601);
        });
    }

    private function resolvePermissions(array $permissions): Collection
    {
        return collect($permissions)->map(function (string|Permission|FolderPermission $permission) {
            if ($permission instanceof FolderPermission) {
                $permission = $permission->name;
            }

            if ($permission instanceof Permission) {
                return $permission->value;
            }

            return Permission::from($permission)->value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return $this->permissions->getIterator();
    }

    public static function fromRequest(Request|array|string $request, string $key = 'permissions'): UAC
    {
        $permissions = $request;

        if (is_object($request)) {
            $permissions = $request->input($key, []);
        }

        if (is_string($request)) {
            $permissions = [$request];
        }

        if (in_array('*', $permissions, true)) {
            return self::all();
        }

        return collect(self::externalToInternalIdentifiersMap())
            ->only($permissions)
            ->values()
            ->pipe(fn ($collection) => new UAC($collection->all()));
    }

    /**
     * @return array<string,string>
     */
    private static function externalToInternalIdentifiersMap(): array
    {
        $ids = [
            'addBookmarks'            => Permission::ADD_BOOKMARKS->value,
            'removeBookmarks'         => Permission::DELETE_BOOKMARKS->value,
            'inviteUsers'             => Permission::INVITE_USER->value,
            'updateFolderName'        => Permission::UPDATE_FOLDER_NAME->value,
            'updateFolderDescription' => Permission::UPDATE_FOLDER_DESCRIPTION->value,
            'updateFolderIcon'        => Permission::UPDATE_FOLDER_ICON->value,
            'removeUser'              => Permission::REMOVE_USER->value,
            'suspendUser'             => Permission::SUSPEND_USER->value
        ];

        assert(count($ids) === count(Permission::cases()));

        return $ids;
    }

    /**
     * @return array<string>
     */
    public static function validExternalIdentifiers(): array
    {
        return array_keys(self::externalToInternalIdentifiersMap());
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

    public function except(Permission|array $permissions): UAC
    {
        $permissions = $this->resolvePermissions(Arr::wrap($permissions));

        return new UAC(
            $this->toCollection()->diff($permissions)->all()
        );
    }

    /**
     * @return array<string>
     */
    public function toExternalIdentifiers(): array
    {
        return collect(self::externalToInternalIdentifiersMap())
            ->flip()
            ->only($this->toArray())
            ->values()
            ->all();
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

    public function isEmpty(): bool
    {
        return $this->permissions->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->permissions->isNotEmpty();
    }

    public function hasAll(UAC $uac = null): bool
    {
        $uac ??= self::all();

        if ($uac->isEmpty()) {
            return false;
        }

        return $this->permissions->intersect($uac)->count() === $uac->count();
    }

    public function hasAny(UAC $uac): bool
    {
        foreach ($uac->permissions as $permission) {
            if ($this->permissions->contains($permission)) {
                return true;
            }
        }

        return false;
    }

    public function has(Permission|string|FolderPermission $permission): bool
    {
        return $this->permissions->containsStrict((new self($permission))->permissions->sole());
    }
}
