<?php

declare(strict_types=1);

namespace App\Http\Handlers\WhitelistDomain;

use App\Models\BlacklistedDomain;
use App\Models\Folder;

final class WhitelistDomain
{
    public function __construct(private readonly BlacklistedDomainRepository $repository)
    {
    }

    public function __invoke(Folder $folder): void
    {
        BlacklistedDomain::query()->whereKey($this->repository->getRecord()->id)->delete();

        $folder->touch('updated_at');
    }
}
