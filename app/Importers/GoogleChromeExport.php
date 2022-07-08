<?php

declare(strict_types=1);

namespace App\Importers;

use App\Contracts\ImporterInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use DOMElement;
use DOMXPath;
use Illuminate\Support\LazyCollection;

final class GoogleChromeExport implements ImporterInterface
{
    public function __construct(private CreateBookmarkService $createBookmark)
    {
    }

    public function importBookmarks(array $requestData): void
    {
        $dOMXPath = $this->parseDOMXPath($requestData);

        LazyCollection::make(function () use ($dOMXPath) {
            foreach ($dOMXPath->query('//dt/a')->getIterator() as $dOMElement) {
                yield $dOMElement;
            }
        })->each(function (DOMElement $dOMElement) use ($requestData) {
            $link = $dOMElement->getAttribute('href');

            if (!Url::isValid($link)) {
                return;
            }

            $url = new Url($link);

            $bookmark = (new BookmarkBuilder())
                ->title($url->value)
                ->hasCustomTitle(false)
                ->url($url->value)
                ->previewImageUrl('')
                ->description(null)
                ->descriptionWasSetByUser(false)
                ->bookmarkedById(UserID::fromAuthUser()->toInt())
                ->site(SiteBuilder::new()->domainName($url->getHostName())->name($url->value)->build())
                ->tags($requestData['tags'] ?? [])
                ->bookmarkedOn($this->resolveTimestamp($requestData, $dOMElement))
                ->build();

            $this->createBookmark->create($bookmark);
        });
    }

    private function parseDOMXPath(array $requestData): DOMXPath
    {
        /** @var  \Illuminate\Http\UploadedFile */
        $file = $requestData['export_file'];

        libxml_use_internal_errors(true);

        $documnet = new \DOMDocument();
        $documnet->loadHTML($file->getContent());

        return new DOMXPath($documnet);
    }

    private function resolveTimestamp(array $requestData, DOMElement $dOMElement): string
    {
        $default = (string) now();
        $addDate = $dOMElement->getAttribute('add_date');

        $useTimestamp = data_get($requestData, 'use_timestamp', false);

        if ($useTimestamp === false || blank($addDate)) {
            return $default;
        }

        try {
            return (string) Carbon::parse($addDate);
        } catch (InvalidFormatException) {
            return  $default;
        }
    }
}
