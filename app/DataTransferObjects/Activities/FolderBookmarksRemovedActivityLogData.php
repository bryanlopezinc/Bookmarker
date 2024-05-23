<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activities;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

final class FolderBookmarksRemovedActivityLogData implements Arrayable
{
    /**
     * @param Collection<Bookmark> $bookmarks
     */
    public function __construct(public readonly Collection $bookmarks, public readonly User $collaborator)
    {
    }

    public static function fromArray(array $data): self
    {
        $bookmarks = collect($data['bookmarks'])
            ->mapInto(Bookmark::class)
            ->each(function (Bookmark $bookmark) {
                $bookmark->exists = true;
            });

        $collaborator = new User($data['collaborator']);

        $collaborator->exists = true;

        return new FolderBookmarksRemovedActivityLogData($bookmarks, $collaborator);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'version'      => '1.0.0',
            'collaborator' => $this->collaborator->activityLogContextVariables(),
            'bookmarks'    => $this->bookmarks->map(function (Bookmark $bookmark) {
                return $bookmark->activityLogContextVariables();
            })->all()
        ];
    }
}
