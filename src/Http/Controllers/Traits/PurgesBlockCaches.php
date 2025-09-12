<?php

namespace A17\Twill\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait PurgesBlockCaches
{
    protected function purgeBlockCachesFor(string $modelType, int|string $pageId, ?string $editorName = null): void
    {
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        // Escape LIKE wildcards: % and _
        $esc = fn (string $v) => str_replace(['%', '_'], ['\\%', '\\_'], $v);

        $modelType  = $esc($modelType);
        $pageId     = $esc((string)$pageId);
        $editorName = $editorName ? $esc($editorName) : null;

        dd($modelType, $pageId, $editorName, $table, $prefix);

        $patterns = [
            "{$prefix}_block_{$modelType}_{$pageId}_%",
            "{$prefix}_blocks_structure_{$modelType}_{$pageId}_%",
            "{$prefix}_block_renderer_{$modelType}_{$pageId}_%",
        ];

        \Log::info('Purging block caches for: ' . implode(', ', $patterns));

        if ($editorName) {
            $patterns[] = "{$prefix}_block_renderer_{$modelType}_{$pageId}_{$editorName}";
            $patterns[] = "{$prefix}_blocks_structure_{$modelType}_{$pageId}_{$editorName}";
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
