<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Carbon\Carbon;

final class TimeStamp
{
    public readonly Carbon $timeStamp;

    public function __construct(string $timestamp)
    {
        $this->timeStamp = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp);
    }
}
