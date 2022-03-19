<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Facades\Validator;

final class BookmarkTitle
{
    public const MAX = 100;

    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    public static function rules(): array
    {
        return [
            'filled', 'string', 'max:' . self::MAX
        ];
    }

    private function validate(): void
    {
        if (Url::isValid($this->value)) {
            return;
        }

        $attribute = 'bookmarkTitle';

        $validator = Validator::make([$attribute => $this->value], [$attribute => ['bail', ...static::rules()]]);

        if ($validator->fails()) {
            $errorCodes = [
                'Filled' => 5000,
                'Max' => 5001,
               // 'AlphaDash' => 5002,
            ];

            throw new \InvalidArgumentException(
                (string) $validator->errors()->get($attribute)[0],
                $errorCodes[array_key_first($validator->failed()[$attribute])]
            );
        }
    }
}
