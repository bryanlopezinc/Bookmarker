<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString as SiteName;
use App\ValueObjects\ResourceID;
use Carbon\Carbon;

final class Source extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly DomainName $domainName;
    public readonly SiteName $name;
    public readonly bool $nameHasBeenUpdated;
    public readonly Carbon $nameUpdatedAt;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(protected array $attributes)
    {
        foreach ($this->attributes as $key => $value) {
            $this->{$key} = $value;
        }

        parent::__construct();
    }
}
