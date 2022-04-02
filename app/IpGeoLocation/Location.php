<?php

declare(strict_types=1);

namespace App\IpGeoLocation;

final class Location
{
    public function __construct(public readonly ?string $country, public readonly ?string $city)
    {
    }

    public static function unknown(): self
    {
        return new self(null, null);
    }

    public function isUnknown(): bool
    {
        return $this->cityIsKnown() == false && $this->countryIsKnown() == false;
    }

    public function countryIsKnown(): bool
    {
        return !is_null($this->country);
    }

    public function cityIsKnown(): bool
    {
        return !is_null($this->city);
    }
}
