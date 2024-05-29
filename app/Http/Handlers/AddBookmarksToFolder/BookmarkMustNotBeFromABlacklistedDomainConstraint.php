<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\{Bookmark, Folder};
use App\ValueObjects\Url;
use Illuminate\Support\{Str, Collection};
use App\Exceptions\HttpException;
use Illuminate\Database\Eloquent\{Builder, Model, Scope};

final class BookmarkMustNotBeFromABlacklistedDomainConstraint implements Scope
{
    /**
     * @param Collection<Bookmark> $bookmarks
     */
    public function __construct(private readonly Collection $bookmarks)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $bookmarksDomainsHashes = $this
            ->bookmarks
            ->pluck('url')
            ->map(fn (string $url) => (new Url($url))->getDomain()->getRegisterableHash())
            ->all();

        $builder->with('blacklistedDomains', function ($query) use ($bookmarksDomainsHashes) {
            $query->select(['folder_id', 'resolved_domain'])->whereIn('domain_hash', $bookmarksDomainsHashes);
        });
    }

    public function __invoke(Folder $folder): void
    {
        $folder->blacklistedDomains->whenNotEmpty(function (Collection $blacklist) {
            throw HttpException::forbidden([
                'message' => 'DomainBlackListed',
                'info'    => sprintf(
                    'Could not add %s to folder because the %s [%s] %s blacklisted.',
                    Str::plural('bookmark', $blacklist->count()),
                    Str::plural('domain', $blacklist->count()),
                    $blacklist->pluck('resolved_domain')->implode(','),
                    $blacklist->count() > 1 ? 'are' : 'is'
                )
            ]);
        });
    }
}
