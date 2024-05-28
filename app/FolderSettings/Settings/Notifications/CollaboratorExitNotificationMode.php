<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\Enums\CollaboratorExitNotificationMode as Mode;

final class CollaboratorExitNotificationMode extends AbstractSetting
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
