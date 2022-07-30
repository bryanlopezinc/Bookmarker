<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TagsDetachedEvent;
use App\Models\Tag;
use App\Models\Taggable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class DeleteUnusedTags //implements ShouldQueue
{
    public function handle(TagsDetachedEvent $event): void
    {
        Tag::select('id')
            ->where('created_by', $event->userID->toInt())
            ->whereIn('name', $event->tags->toStringCollection())
            ->whereNotIn('id', Taggable::select('tag_id'))
            ->delete();
    }
}
