<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidTagException;
use Illuminate\Support\Facades\Validator;

final class Tag
{
    public const MAX_LENGTH = 22;

    public readonly string $value;
    private static array $checked = [];

    public function __construct(string|int $value)
    {
        $this->value = mb_strtolower((string) $value);

        $this->validate();
    }

    /**
     * @return array<string>
     */
    public static function rules(array $merge = []): array
    {
        return [
            'max:' . self::MAX_LENGTH, 'filled', 'alpha_num', ...$merge
        ];
    }

    private function validate(): void
    {
        if (isset(static::$checked[$this->value])) return;

        $attribute = 'tag';

        $rules = $this->rules(['bail']);

        $validator = Validator::make([$attribute => $this->value], [$attribute => $rules]);

        if ($validator->fails()) {
            $errorCodes = [
                'Filled' => InvalidTagException::EMPTY_TAG_CODE,
                'Max' => InvalidTagException::INVALID_MAX_LENGHT_CODE,
                'AlphaNum' => InvalidTagException::APLHA_NUM_CODE,
            ];

            throw new InvalidTagException(
                (string) $validator->errors()->get($attribute)[0],
                $errorCodes[array_key_first($validator->failed()[$attribute])]
            );
        }

        static::$checked[$this->value] = true;
    }
}
