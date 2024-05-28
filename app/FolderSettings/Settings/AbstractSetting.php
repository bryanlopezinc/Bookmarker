<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

use App\Exceptions\InvalidFolderSettingException;
use App\FolderSettings\SettingInterface;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

abstract class AbstractSetting implements SettingInterface
{
    public function __construct(protected readonly mixed $value = null)
    {
        $this->validate($value);
    }

    abstract public function value(): mixed;

    abstract protected function rules(): array;

    abstract protected function id(): string;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $settings): static
    {
        $settingId = (new static())->id(); //@phpstan-ignore-line

        return new static(Arr::get($settings, $settingId)); //@phpstan-ignore-line
    }

    protected function validate(mixed $value): void
    {
        $data = [];

        Arr::set($data, $this->id(), $value);

        $validator = Validator::make(
            data: $data,
            rules: [$this->id() => $this->rules()],
            attributes: [$this->id() => $this->id()]
        );

        if ($validator->fails()) {
            throw new InvalidFolderSettingException($validator->errors()->all());
        }
    }

    /**
     * @inheritdoc
     */
    public function toArray(): mixed
    {
        $result = [];

        $value = $this->value();

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        Arr::set($result, $this->id(), $value);

        return $result;
    }
}
