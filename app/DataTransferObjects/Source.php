<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString as SiteName;
use App\ValueObjects\ResourceID;
use Carbon\Carbon;

final class Source extends DataTransferObject
{
    use Constructor;

    public readonly ResourceID $id;
    public readonly DomainName $domainName;
    public readonly SiteName $name;
    public readonly bool $nameHasBeenUpdated;
    public readonly Carbon $nameUpdatedAt;
}
