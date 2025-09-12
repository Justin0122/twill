<?php

namespace A17\Twill\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait PurgesBlockCaches
{
    protected function purgeBlockCachesFor(string $modelType, int|string $pageId, ?string $editorName = null): void
    {
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        // Escape only LIKE wildcards. Do NOT touch backslashes.
        $esc = fn (string $v) => str_replace(['%','_'], ['\\%','\\_'], $v);

        $modelType  = $esc($modelType);          // e.g. App\Models\Page
        $pageId     = $esc((string)$pageId);     // e.g. 1
        $editorName = $editorName ? $esc($editorName) : null;

        // Base (underscore) patterns
        $patterns = [
            "{$prefix}block_{$modelType}_{$pageId}\\_%",
            "{$prefix}blocks_structure_{$modelType}_{$pageId}\\_%",
            "{$prefix}block_renderer_{$modelType}_{$pageId}\\_%",
        ];


        if ($editorName) {
            $patterns[] = "{$prefix}_block_renderer_{$modelType}_{$pageId}\\_{$editorName}";
            $patterns[] = "{$prefix}_blocks_structure_{$modelType}_{$pageId}\\_{$editorName}";
        }

        DB::table($table)->where(function ($q) use ($patterns) {
            foreach ($patterns as $p) {
                $q->orWhere('key', 'like', $p);
            }
        })->delete();
    }

    protected function purgeAllBlockPreviews(): void
    {
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        DB::table($table)
            ->where('key', 'like', "{$prefix}_block_preview\\_%")
            ->delete();
    }
}
