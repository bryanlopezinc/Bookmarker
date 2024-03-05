<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateFolder;

use App\DataTransferObjects\CreateFolderData;
use App\Utils\FolderSettingsNormalizer;

final class NormalizeFolderSettings implements HandlerInterface
{
    private readonly FolderSettingsNormalizer $normalizer;
    private readonly HandlerInterface $handler;

    public function __construct(HandlerInterface $handler, FolderSettingsNormalizer $normalizer = null)
    {
        $this->handler = $handler;
        $this->normalizer = $normalizer ??= new FolderSettingsNormalizer();
    }

    public function create(CreateFolderData $data): void
    {
        $normalizedSettings = $this->normalizer->fromRequest($data->settings);

        $data = CreateFolderData::fromArray(
            array_replace($data->toArray(), ['settings' => $normalizedSettings])
        );

        $this->handler->create($data);
    }
}
