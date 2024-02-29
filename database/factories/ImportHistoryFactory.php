<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Importing\Enums\BookmarkImportStatus;
use App\Importing\Models\ImportHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportHistory>
 */
final class ImportHistoryFactory extends Factory
{
    protected $model = ImportHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'import_id'             => $this->faker->uuid,
            'url'                   => $this->faker->url,
            'document_line_number'  => $this->faker->randomDigitNotNull,
            'tags'                  => [],
            'status'                => BookmarkImportStatus::SUCCESS,
        ];
    }

    public function failed(BookmarkImportStatus $reason = BookmarkImportStatus::FAILED_DUE_TO_SYSTEM_ERROR): self
    {
        return $this->state(['status' => $reason->value]);
    }

    public function skipped(BookmarkImportStatus $reason = BookmarkImportStatus::SKIPPED_DUE_TO_INVALID_TAG): self
    {
        return $this->state(['status' => $reason->value]);
    }
}
