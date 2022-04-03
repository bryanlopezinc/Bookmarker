<?php

declare(strict_types=1);

namespace App\IpGeoLocation\IpApi;

use App\IpGeoLocation\IpAddress;
use App\IpGeoLocation\IpGeoLocatorInterface;
use App\IpGeoLocation\Location;
use Illuminate\Support\Facades\Http;

final class IpGeoLocationHttpClient implements IpGeoLocatorInterface
{
    public function getLocationFromIp(IpAddress $ipAddress): Location
    {
        $response = Http::retry(2)->get('http://ip-api.com/json/' . $ipAddress->value, [
            'fields' => 'country,city'
        ]);

        if (!$response->successful() || $response->json('status') === 'fail') {
            return Location::unknown();
        }

        return new Location($response->json('country'), $response->json('city'));
    }
}
