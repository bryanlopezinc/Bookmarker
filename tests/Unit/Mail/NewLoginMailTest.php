<?php

namespace Tests\Unit\Mail;

use App\DeviceDetector\Device;
use App\DeviceDetector\DeviceType;
use App\IpGeoLocation\Location;
use App\LoginInformation;
use App\Mail\NewLoginMail;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NewLoginMailTest extends TestCase
{
    public function testWillRenderMail(): void
    {
        $loginData = new LoginInformation(
            new Device(DeviceType::MOBILE, 'IPhone'),
            new Location('France', 'Paris')
        );

        $page = (new NewLoginMail($loginData))->render();

        (new TestResponse(new Response($page)))
            ->assertSee('Device: Mobile')
            ->assertSee('DeviceName: IPhone')
            ->assertSee('Country: France')
            ->assertSee('City: Paris');
    }

    public function testWillNotShowDeviceInfoWhenDeviceIsUnknown(): void
    {
        $loginData = new LoginInformation(
            new Device(DeviceType::UNKNOWN, null),
            new Location('France', 'Paris')
        );

        $page = (new NewLoginMail($loginData))->render();

        (new TestResponse(new Response($page)))
            ->assertDontSee('DeviceName:')
            ->assertSee('Device: Unknown');
    }

    public function testWillNotShowLocationInfoWhenLocationIsUnknown(): void
    {
        $loginData = new LoginInformation(
            new Device(DeviceType::UNKNOWN, null),
            Location::unknown()
        );

        $page = (new NewLoginMail($loginData))->render();
        (new TestResponse(new Response($page)))
            ->assertDontSee('Country')
            ->assertDontSee('City');
    }
}
