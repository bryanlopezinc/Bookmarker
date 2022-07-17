<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString as SiteName;
use App\ValueObjects\ResourceID;

final class WebSite extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly DomainName $domainName;
    public readonly SiteName $name;
    public readonly bool $nameHasBeenUpdated;
}
