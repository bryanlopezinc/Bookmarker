<?php

declare(strict_types=1);

namespace App;

use App\DeviceDetector\Device;
use App\IpGeoLocation\Location;

final class LoginInformation
{
    public function __construct(public readonly Device $device, public readonly Location $location)
    {
    }
}
