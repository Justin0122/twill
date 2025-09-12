<?php

namespace A17\Twill\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait PurgesBlockCaches
{
    protected function purgeBlockCachesFor(string $modelType, int|string $pageId, ?string $editorName = null): void
    {
        $table = config('cache.stores.database.table', 'cache');
        $regexp = "Page_{$pageId}_[^0-9]";

        DB::delete("DELETE FROM `{$table}` WHERE `key` REGEXP ?", [$regexp]);
    }


    protected function purgeAllBlockPreviews(): void
    {
        $table = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        DB::delete("DELETE FROM `{$table}` WHERE `key` LIKE ?", ["{$prefix}_block_preview\\_%"]);
    }

}
