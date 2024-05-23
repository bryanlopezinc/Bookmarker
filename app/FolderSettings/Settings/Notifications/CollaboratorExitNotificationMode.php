<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\CollaboratorExitNotificationMode as Mode;

final class CollaboratorExitNotificationMode extends AbstractSetting
{
    public function value(): Mode
    {
        if ( ! $this->isSet()) {
            return Mode::ALL;
        }

        return Mode::from($this->value);
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'notifications.collaborator_exit.mode';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return ['sometimes', 'string', 'in:*,hasWritePermission'];
    }
}
