<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\Device;
use App\DataTransferObjects\Location;

final class LoginInformation
{
    public function __construct(public readonly Device $device, public readonly Location $location)
    {
    }
}
