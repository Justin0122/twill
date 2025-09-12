<?php

namespace A17\Twill\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

trait PurgesBlockCaches
{
    protected function purgeBlockCachesFor(string $modelType, int|string $pageId, ?string $editorName = null): void
    {
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        $esc = fn (string $v) => str_replace(['%', '_'], ['\\%', '\\_'], $v);

        $modelType  = $esc($modelType);
        $pageId     = $esc((string)$pageId);
        $editorName = $editorName ? $esc($editorName) : null;

        $patterns = [
            "{$prefix}block_renderer_{$modelType}_{$pageId}_%",
        ];

        if ($editorName) {
            $patterns[] = "{$prefix}block_renderer_{$modelType}_{$pageId}_{$editorName}";
        }

        foreach ($patterns as $pattern) {
            $keys = DB::table($table)
                ->where('key', 'like', $pattern)
                ->pluck('key');

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    protected function purgeAllBlockPreviews(): void
    {
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        $keys = DB::table($table)
            ->where('key', 'like', "{$prefix}_block_preview\\_%")
            ->pluck('key');

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
