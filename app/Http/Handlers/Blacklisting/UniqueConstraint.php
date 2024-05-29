<?php

declare(strict_types=1);

namespace App\Http\Handlers\Blacklisting;

use App\Exceptions\HttpException;
use App\Models\BlacklistedDomain;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\ValueObjects\Url;

final class UniqueConstraint implements Scope
{
    public function __construct(private readonly Url $url)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'domainIsAlreadyBlacklisted' => BlacklistedDomain::query()
                ->selectRaw('1')
                ->whereColumn('folder_id', 'folders.id')
                ->where('domain_hash', $this->url->getDomain()->getRegisterableHash())
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->domainIsAlreadyBlacklisted) {
            throw HttpException::conflict(['message' => 'DomainAlreadyBlacklisted']);
        }
    }
}
