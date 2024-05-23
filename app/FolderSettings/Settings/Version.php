<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

final class Version extends AbstractSetting
{
    public function value(): string
    {
        if ( ! $this->isSet()) {
            return '1.0.0';
        }

        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'version';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return ['string', 'in:1.0.0'];
    }
}
