<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

final class Version extends AbstractSetting
{
    /**
     * @param string $version
     */
    public function __construct($version = '1.0.0')
    {
        parent::__construct($version);
    }

    public function value(): string
    {
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
