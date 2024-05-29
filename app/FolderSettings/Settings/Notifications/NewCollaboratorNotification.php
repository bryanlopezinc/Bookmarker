<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Notifications;

use App\FolderSettings\Settings\AbstractSetting;
use App\FolderSettings\Settings\Concerns\HasBooleanValue;

final class NewCollaboratorNotification extends AbstractSetting
{
    use HasBooleanValue;

    /**
     * @param bool $enable
     */
    public function __construct($enable = true)
    {
        parent::__construct($enable);
    }

    public function isDisabled(): bool
    {
        return ! $this->value();
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'notifications.new_collaborator.enabled';
    }

    public function value(): bool
    {
        return $this->normalize($this->value);
    }
}