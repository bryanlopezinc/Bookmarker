<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\IdGeneratorInterface;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
final class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        /** @var IdGeneratorInterface */
        $generator = app(IdGeneratorInterface::class);

        return [
            'public_id'   => $generator->generate(),
            'user_id'     => UserFactory::new(),
            'status'      => ImportBookmarksStatus::PENDING,
            'statistics'  => new ImportStats(),
            'created_at'  => now()
        ];
    }

    public function importing(): self
    {
        return $this->state(['status' => ImportBookmarksStatus::IMPORTING]);
    }

    public function failed(
        ImportStats $importStats = new ImportStats(),
        ImportBookmarksStatus $reason = ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR
    ): self {
        return $this->state([
            'status'     => $reason->value,
            'statistics' => $importStats,
        ]);
    }

    public function pending(): self
    {
        return $this->state(['status' => ImportBookmarksStatus::PENDING]);
    }

    public function successful(ImportStats $importStats = new ImportStats()): self
    {
        return $this->state([
            'status'     => ImportBookmarksStatus::SUCCESS,
            'statistics' => $importStats
        ]);
    }
}
