<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportBookmarkRequest extends FormRequest
{
    /** import sources */
    public const CHROME = 'chromeExportFile';
    public const POCKET = 'pocketExportFile';
    public const SAFARI = 'safariExportFile';
    public const INSTAPAPER = 'instapaperFile';

    /**
     * @var array<string,string>
     */
    private const VALIDATORS = [
        self::CHROME => Imports\ChromeImportRequestValidator::class,
        self::POCKET => Imports\PocketImportRequestValidator::class,
        self::SAFARI => Imports\SafariImportRequestValidator::class,
        self::INSTAPAPER => Imports\InstapaperRequestValidator::class
    ];

    public function rules(): array
    {
        return array_merge($this->getSourceValidator()->rules(), [
            'source' => [
                'required', 'string', 'filled', Rule::in([
                    self::CHROME,
                    self::POCKET,
                    self::SAFARI,
                    self::INSTAPAPER
                ])
            ]
        ]);
    }

    private function getSourceValidator(): Imports\RequestValidatorInterface
    {
        $validatorClass = self::VALIDATORS[$this->input('source', 100)] ?? false;

        if ($validatorClass == false) {
            return new Imports\EmptyValidator;
        }

        return app($validatorClass);
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $requestValidator = $this->getSourceValidator();

        if ($requestValidator instanceof Imports\AfterValidationInterface) {
            $requestValidator->withValidator($validator);
        }
    }
}
