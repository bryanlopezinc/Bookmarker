<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\NewCollaboratorNotificationMode as Mode;

final class NewCollaboratorNotificationMode extends AbstractSetting
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
        return 'notifications.new_collaborator.mode';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return ['sometimes', 'string', 'in:*,invitedByMe'];
    }
}
