<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\CollaboratorExitNotificationMode as Mode;

final class CollaboratorExitNotificationMode extends AbstractSetting
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
