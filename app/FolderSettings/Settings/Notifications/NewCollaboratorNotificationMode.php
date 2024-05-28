<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\NewCollaboratorNotificationMode as Mode;

final class NewCollaboratorNotificationMode extends AbstractSetting
{
    /**
     * @param string|null $mode
     */
    public function __construct($mode = null)
    {
        parent::__construct($mode ?? Mode::ALL->value);
    }

    public function value(): Mode
    {
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
