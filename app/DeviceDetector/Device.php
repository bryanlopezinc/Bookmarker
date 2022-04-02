<?php

declare(strict_types=1);

namespace App\DeviceDetector;

final class Device
{
    public function __construct(public readonly DeviceType $type, public readonly ?string $name)
    {
    }

    public function nameIsKnown(): bool
    {
        return $this->name !== null;
    }
}
