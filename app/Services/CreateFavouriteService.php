<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Bookmark;
use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\FavouritesRepository;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class CreateFavouriteService
{
    public function __construct(private FavouritesRepository $repository, private BookmarksRepository $bookmarkRepository)
    {
    }

    public function create(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarkRepository->findManyById($bookmarkIDs, BookmarkQueryColumns::new()->userId()->id());

        //throw exception if some bookmarkIDs does not exists.
        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            $this->throwException($this->prepareNotFoundResponseMessage($bookmarkIDs, $bookmarks), Response::HTTP_NOT_FOUND);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsBookmark);

        $userId = UserID::fromAuthUser();

        $duplicates = $this->repository->duplicates($userId, $bookmarkIDs);

        if ($duplicates->isNotEmpty()) {
            $this->throwException($this->prepareHttpConflictMessage($duplicates), Response::HTTP_CONFLICT);
        }

        $this->repository->createMany($bookmarkIDs, $userId);
    }

    private function prepareHttpConflictMessage(ResourceIDsCollection $bookmarkIds): string
    {
        return sprintf('could not add ids [%s] because they have already been added to favourites', $bookmarkIds->asIntegers()->implode(', '));
    }

    /**
     * @param Collection<Bookmark> $result
     */
    private function prepareNotFoundResponseMessage(ResourceIDsCollection $bookmarkIDs, Collection $result): string
    {
        return sprintf(
            "could not add ids [%s] because they do not exists",
            $bookmarkIDs->asIntegers()->diff($result->map(fn (Bookmark $bookmark) => $bookmark->id->toInt()))->implode(', ')
        );
    }

    private function throwException(mixed $message, int $status): void
    {
        throw new HttpResponseException(response()->json($message, $status));
    }
}
