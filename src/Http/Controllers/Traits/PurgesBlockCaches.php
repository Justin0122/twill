<?php

namespace A17\Twill\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait PurgesBlockCaches
{
    protected function purgeBlockCachesFor(string $modelType, int|string $pageId, ?string $editorName = null): void
    {
        // DB cache table & prefix
        $table  = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');

        // Escape LIKE wildcards just in case (MySQL: \% and \_ act as literals)
        $esc = fn (string $v) => str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $v);

        $modelType = $esc($modelType);
        $pageId    = $esc((string) $pageId);

        // Base patterns (page-scoped)
        $patterns = [
            "{$prefix}_block_{$modelType}_{$pageId}\\_%",           // laravel_cache_block_{type}_{page}_{blockId}
            "{$prefix}_blocks_structure_{$modelType}_{$pageId}\\_%",// laravel_cache_blocks_structure_{type}_{page}_{editor}
            "{$prefix}_block_renderer_{$modelType}_{$pageId}\\_%",  // laravel_cache_block_renderer_{type}_{page}_{editor}
        ];

        // Optionally narrow to a given editor
        if ($editorName) {
            $editorName = $esc($editorName);
            $patterns[] = "{$prefix}_block_renderer_{$modelType}_{$pageId}\\_{$editorName}";
            $patterns[] = "{$prefix}_blocks_structure_{$modelType}_{$pageId}\\_{$editorName}";
        }

        // Delete matching rows
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
