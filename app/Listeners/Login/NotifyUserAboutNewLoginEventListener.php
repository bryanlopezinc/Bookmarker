<?php

declare(strict_types=1);

namespace App\Listeners\Login;

use App\Contracts\DeviceDetectorInterface;
use App\Contracts\IpGeoLocatorInterface;
use App\ValueObjects\Device;
use App\Events\LoginEvent;
use App\DataTransferObjects\Location;
use App\Enums\DeviceType;
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
        if ( ! $event->userAgent) {
            return new Device(DeviceType::UNKNOWN, null);
        }

        return $this->deviceDetector->fromUserAgent($event->userAgent);
    }

    private function getLocation(LoginEvent $event): Location
    {
        if ( ! $event->ipAddress) {
            return Location::unknown();
        }

        return $this->ipGeoLocator->getLocationFromIp($event->ipAddress);
    }
}
