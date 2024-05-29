<?php

declare(strict_types=1);

namespace App\Enums;

enum IdPrefix: string
{
    case FOLDER             = 'f_';
    case USER               = 'ur_';
    case ROLE               = 'rl_';
    case BOOKMARK           = 'b_';
    case BOOKMARK_SOURCE    = 'bs_';
    case IMPORT             = 'im_';
    case BLACKLISTED_DOMAIN = 'bd_';
}
