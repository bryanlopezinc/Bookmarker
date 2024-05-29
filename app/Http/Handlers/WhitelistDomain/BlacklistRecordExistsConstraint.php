<?php

declare(strict_types=1);

namespace App\Http\Handlers\WhitelistDomain;

use App\Exceptions\HttpException;
use App\Models\Folder;

final class BlacklistRecordExistsConstraint
{
    public function __construct(private readonly BlacklistedDomainRepository $repository)
    {
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $this->repository->exists()) {
            throw HttpException::notFound(['message' => 'RecordNotFound']);
        }
    }
}
