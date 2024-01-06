<?php

declare(strict_types=1);

namespace App\Jobs;

use App\HealthChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Bookmark;
use Illuminate\Support\Collection;

final class CheckBookmarksHealth implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var Collection<Bookmark>
     */
    private Collection $bookmarks;

    public function __construct(iterable $bookmarks)
    {
        $this->bookmarks = collect($bookmarks);
    }

    public function handle(HealthChecker $healthChecker): void
    {
        $healthChecker->ping($this->bookmarks->all());
    }
}
