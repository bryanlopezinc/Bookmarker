<?php

declare(strict_types=1);

namespace App\Listeners\Login;

use App\DeviceDetector\Device;
use App\DeviceDetector\DeviceDetectorInterface;
use App\DeviceDetector\DeviceType;
use App\Events\LoginEvent;
use App\IpGeoLocation\IpGeoLocatorInterface;
use App\IpGeoLocation\Location;
use App\LoginInformation;
use App\Mail\NewLoginMail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyUserAboutNewLoginEventListener implements ShouldQueue
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly DeviceDetectorInterface $deviceDetector,
        private readonly IpGeoLocatorInterface $ipGeoLocator
    ) {
    }

    public function handle(LoginEvent $event): void
    {
        $loginInfo = new LoginInformation($this->getDeviceInfo($event), $this->getLocation($event));

        $this->mailer->to($event->user->email)->send(new NewLoginMail($loginInfo));
    }

    private function getDeviceInfo(LoginEvent $event): Device
    {
        if (!$event->hasUserAgentInfo()) {
            return new Device(DeviceType::UNKNOWN, null);
        }

        // userAgent already null checked.
        // @phpstan-ignore-next-line
        return $this->deviceDetector->fromUserAgent($event->userAgent);
    }

    private function getLocation(LoginEvent $event): Location
    {
        if (!$event->hasIpAddressInfo()) {
            return Location::unknown();
        }

        // ipAddress already null checked.
        // @phpstan-ignore-next-line
        return $this->ipGeoLocator->getLocationFromIp($event->ipAddress);
    }
}
