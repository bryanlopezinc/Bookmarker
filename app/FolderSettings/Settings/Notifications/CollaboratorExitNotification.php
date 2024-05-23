<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\FolderSettings\Settings\Concerns\HasBooleanValue;

final class CollaboratorExitNotification extends AbstractSetting
{
    use HasBooleanValue;

    public function isDisabled(): bool
    {
        return ! $this->value();
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'notifications.collaborator_exit.enabled';
    }

    public function value(): bool
    {
        if ( ! $this->isSet()) {
            return true;
        }

        return $this->normalize($this->value);
    }
}
