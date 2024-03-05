<?php

declare(strict_types=1);

namespace App\Rules\FolderSettings;

use App\Contracts\FolderSettingSchemaProviderInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;

final class FolderSettingsRootNodesRule implements ValidationRule, DataAwareRule
{
    public bool $implicit = true;
    private array $data;
    private readonly FolderSettingSchemaProviderInterface $schema;

    public function __construct(FolderSettingSchemaProviderInterface $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @inheritdoc
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        $data = $this->data;

        if (array_key_exists($attribute, $this->data)) {
            $data = $this->data[$attribute];
        }

        foreach ($data as $key => $value) {
            if (!$this->schema->exists((string) $key)) {
                $fail("The given setting {$key} is invalid.");
            }
        }
    }
}
