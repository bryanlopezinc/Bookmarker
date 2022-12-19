<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\InvalidJsonException;
use JsonSchema\Validator;

final class JsonValidator
{
    /**
     * @throws InvalidJsonException
     */
    public function validate(array $data, string $jsonSchema): void
    {
        $validator = new Validator();

        $validationData = json_decode(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES  | JSON_THROW_ON_ERROR
            )
        );

        $validator->validate($validationData, json_decode($jsonSchema, flags: JSON_THROW_ON_ERROR));

        if (!$validator->isValid()) {
            throw new InvalidJsonException(
                'The given settings is invalid. errors : ' . json_encode($validator->getErrors(), JSON_PRETTY_PRINT)
            );
        }
    }
}
