<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings\Settings;

use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\FolderSettings\TestCase;

class EnableNotificationsTest extends TestCase
{
    #[Test]
    public function notificationsIsEnabled(): void
    {
        $settings = $this->make();
        $this->assertFalse($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => false]]);
        $this->assertTrue($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => '0']]);
        $this->assertTrue($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => true]]);
        $this->assertFalse($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => '1']]);
        $this->assertFalse($settings->notifications()->isDisabled());

        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'true']], $error = 'The notifications.enabled field must be true or false.'));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'false']], $error));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => null]], $error));
    }
}
