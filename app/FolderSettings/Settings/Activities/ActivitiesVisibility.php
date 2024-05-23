<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Activities;

use App\Enums\FolderActivitiesVisibility as Visibility;
use App\FolderSettings\Settings\AbstractSetting;
use Illuminate\Validation\Rule;

final class ActivitiesVisibility extends AbstractSetting
{
    public function value(): Visibility
    {
        if ( ! $this->isSet()) {
            return Visibility::PUBLIC;
        }

        if($this->value instanceof Visibility) {
            return $this->value;
        }

        if (is_string($this->value)) {
            return Visibility::fromRequest($this->value);
        }

        return Visibility::from($this->value);
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'activities.visibility';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return [
            Rule::when(
                condition: is_string($this->value),
                rules:['string', Rule::in(Visibility::publicIdentifiers())],
                defaultRules:[Rule::enum(Visibility::class)]
            ),
        ];
    }
}
