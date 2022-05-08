<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString as SiteName;
use App\ValueObjects\ResourceID;
use App\ValueObjects\TimeStamp;

final class WebSite extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly DomainName $domainName;
    public readonly SiteName $name;
    public readonly TimeStamp $timeCreated;
    public readonly TimeStamp $nameUpdatedAt;
    public readonly bool $nameHasBeenUpdated;
}
