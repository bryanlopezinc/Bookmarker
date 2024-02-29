<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Location;
use App\ValueObjects\IpAddress;

interface IpGeoLocatorInterface
{
    public function getLocationFromIp(IpAddress $ipAddress): Location;
}
