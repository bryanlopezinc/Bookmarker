<?php

declare(strict_types=1);

namespace App\DeviceDetector;

enum DeviceType: int
{
    case MOBILE = 1;
    case TABLET = 2;
    case PC = 3;
    case UNKNOWN = 4;
}
