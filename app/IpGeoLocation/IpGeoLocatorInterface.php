<?php

declare(strict_types=1);

namespace App\IpGeoLocation;

interface IpGeoLocatorInterface
{
    public function getLocationFromIp(IpAddress $ipAddress): Location;
}
