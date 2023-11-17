<?php

declare(strict_types=1);

namespace App\Readers;

use App\Models\Bookmark;
use App\ValueObjects\Url;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;

final class DefaultClient implements HttpClientInterface
{
    private DOMReader $dOMReader;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, DOMReader $dOMReader = null)
    {
        $this->logger = $logger;
        $this->dOMReader = $dOMReader ?? new DOMReader();
    }

    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false
    {
        try {
            $response = Http::accept('text/html')
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36') //phpcs:ignore
                ->get($bookmark->url);
        } catch (ConnectionException $e) {
            return $this->handleException($e->getPrevious(), $bookmark);
        }

        if (!$response->successful() || !str_contains($response->header('content-type'), 'text/html')) {
            return $this->emptyResponse($bookmark);
        }

        $this->dOMReader->loadHTML($response->body(), $resolvedUrl = new Url((string)$response->effectiveUri()));

        return BookmarkMetaData::fromArray([
            'title'        => $this->dOMReader->getPageTitle(),
            'description'  => $this->dOMReader->getPageDescription(),
            'imageUrl'     => $this->dOMReader->getPreviewImageUrl(),
            'siteName'     => $this->dOMReader->getSiteName(),
            'canonicalUrl' => $this->dOMReader->getCanonicalUrl(),
            'resolvedUrl'  => $resolvedUrl
        ]);
    }

    private function handleException(ConnectException $exception, Bookmark $bookmark): BookmarkMetaData | false
    {
        $errorCode = $exception->getHandlerContext()['errno'];

        if ($errorCode === \CURLE_COULDNT_RESOLVE_HOST) {
            return  $this->emptyResponse($bookmark);
        }

        if ($errorCode === \CURLE_OPERATION_TIMEOUTED) {
            return false;
        }

        $this->logger->critical($exception->getMessage(), $exception->getHandlerContext());

        return false;
    }

    private function emptyResponse(Bookmark $bookmark): BookmarkMetaData
    {
        return BookmarkMetaData::fromArray([
            'title'        => false,
            'description'  => false,
            'imageUrl'     => false,
            'siteName'     => false,
            'canonicalUrl' => false,
            'resolvedUrl'  => new Url($bookmark->url)
        ]);
    }
}
