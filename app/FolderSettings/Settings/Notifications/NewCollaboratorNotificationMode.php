<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\NewCollaboratorNotificationMode as Mode;

final class NewCollaboratorNotificationMode extends AbstractSetting
{
    /**
     * @param string $mode
     */
    public function __construct($mode = Mode::ALL->value)
    {
        parent::__construct($mode);
    }

    public function value(): Mode
    {
        if ($this->value instanceof Mode) {
            return $this->value;
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
