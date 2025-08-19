<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use A17\Twill\Models\File;
use A17\Twill\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FileFolderController extends Controller
{
    public function index(Request $request)
    {
        $rows = LibraryFolder::where('library', 'file')
            ->orderBy('path')
            ->get(['id', 'name', 'path', 'parent_id']);

        return response()->json(['tree' => $this->buildTreeFromRows($rows)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'   => 'required|in:file',
            'parent' => 'nullable|string',
            'name'   => 'required|string|max:255',
        ]);

        $parentPath = trim((string) ($data['parent'] ?? ''), '/');
        $name       = trim($data['name'], '/');

        $parent = $parentPath === ''
            ? null
            : LibraryFolder::where('library', 'file')->where('path', $parentPath)->first();

        $path = trim(($parent->path ?? '') . '/' . $name, '/');

        $folder = LibraryFolder::firstOrCreate(
            ['library' => 'file', 'path' => $path],
            [
                'name'      => $name,
                'parent_id' => $parent?->id,
            ]
        );

        return response()->json(['ok' => true, 'folder' => $folder], 201);
    }

    // Rename folder
    public function update(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'file', 404);

        $data = $request->validate(['name' => 'required|string|max:255']);

        $oldPath = $folder->path;
        $newPath = trim(($folder->parent?->path ?? '') . '/' . $data['name'], '/');

        if (LibraryFolder::where('library', 'file')->where('path', $newPath)->where('id', '!=', $folder->id)->exists()) {
            return response()->json(['message' => 'A folder with that name already exists here.'], 422);
        }

        DB::transaction(function () use ($folder, $data, $oldPath, $newPath) {
            $folder->update(['name' => $data['name'], 'path' => $newPath]);

            $like = $oldPath === '' ? '%' : $oldPath . '/%';
            $descendants = LibraryFolder::where('library', 'file')
                ->where('path', 'like', $like)
                ->get();

            foreach ($descendants as $child) {
                $child->update([
                    'path' => preg_replace('#^'.preg_quote($oldPath, '#').'#', $newPath, $child->path)
                ]);
            }
        });

        return response()->json(['ok' => true, 'folder' => $folder->fresh()]);
    }

    // Move files to a target folder by ID (or to root if null)
    public function move(Request $request)
    {
        $data = $request->validate([
            'type'       => 'required|in:file',
            'targetId'   => 'nullable|integer',
            'mediaIds'   => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ]);

        $targetId = $data['targetId'] ?? null;
        if ($targetId !== null) {
            $exists = LibraryFolder::where('id', $targetId)->where('library', 'file')->exists();
            if (!$exists) {
                return response()->json(['message' => 'Target folder not found'], 422);
            }
        }

        DB::table('twill_files')->whereIn('id', $data['mediaIds'])
            ->update(['folder_id' => $targetId]); // null => root

        return response()->json(['ok' => true]);
    }

    public function reparent(Request $request)
    {
        $data = $request->validate([
            'sourceId' => 'required|integer',
            'targetId' => 'nullable|integer',
        ]);

        $source = LibraryFolder::where('library', 'file')->find($data['sourceId']);
        if (!$source) return response()->json(['message' => 'Source folder not found'], 404);

        $target = null;
        if (!empty($data['targetId'])) {
            $target = LibraryFolder::where('library', 'file')->find($data['targetId']);
            if (!$target) return response()->json(['message' => 'Target folder not found'], 422);
        }

        $targetPath = trim($target->path ?? '', '/'); // '' = root
        $sourcePath = trim($source->path ?? '', '/');
        if ($targetPath !== '' && ($targetPath === $sourcePath || str_starts_with($targetPath . '/', $sourcePath . '/'))) {
            return response()->json(['message' => 'Cannot move a folder into itself or its descendant.'], 422);
        }

        $newPath = trim(($targetPath ? $targetPath . '/' : '') . $source->name, '/');

        $exists = LibraryFolder::where('library', 'file')
            ->where('path', $newPath)
            ->where('id', '!=', $source->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'A folder with that name already exists in the destination.'], 422);
        }

        $oldPath = $source->path;

        DB::transaction(function () use ($source, $oldPath, $newPath, $target) {
            $source->update([
                'parent_id' => $target?->id,
                'path'      => $newPath,
            ]);

            $like = $oldPath === '' ? '%' : $oldPath . '/%';
            $descendants = LibraryFolder::where('library', 'file')
                ->where('path', 'like', $like)
                ->get();

            foreach ($descendants as $child) {
                $child->update([
                    'path' => preg_replace('#^'.preg_quote($oldPath, '#').'#', $newPath, $child->path)
                ]);
            }
        });

        return response()->json(['ok' => true, 'folder' => $source->fresh()]);
    }

    public function destroy(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'file', 404);

        $ids = collect([$folder->id])
            ->merge(
                LibraryFolder::where('library', 'file')
                    ->where('path', 'like', $folder->path . '/%')
                    ->pluck('id')
            )
            ->unique()
            ->values();

        $fileIds = DB::table('twill_files')
            ->whereIn('folder_id', $ids)
            ->pluck('id');

        if ($fileIds->isEmpty()) {
            LibraryFolder::whereIn('id', $ids)->delete();

            return response()->json([
                'ok' => true,
                'deleted' => ['files' => 0, 'folders' => $ids->count()],
            ]);
        }

        $usages = DB::table('twill_fileables')
            ->whereIn('file_id', $fileIds)
            ->select('file_id', 'fileable_type', 'fileable_id', 'role')
            ->orderBy('file_id')
            ->get();

        if ($usages->count() > 0) {
            $fileMeta = DB::table('twill_files')
                ->whereIn('id', $fileIds)
                ->pluck('filename', 'id');

            $usedReport = $usages->groupBy('file_id')->map(function ($rows, $fileId) use ($fileMeta) {
                $places = $rows->map(function ($row) {
                    [$pageType, $pageId] = $this->resolveUsageToPage($row->fileable_type, (int)$row->fileable_id);

                    $title = null;
                    if ($pageType && class_exists($pageType)) {
                        try {
                            $model = $pageType::find($pageId);
                            if ($model) {
                                $title = $model->title
                                    ?? $model->name
                                    ?? (method_exists($model, 'getTitle') ? $model->getTitle() : null)
                                    ?? (method_exists($model, '__toString') ? (string)$model : null);
                            }
                        } catch (\Throwable $e) {}
                    }

                    return [
                        'type'      => $pageType ?: $row->fileable_type,
                        'id'        => $pageId   ?: $row->fileable_id,
                        'role'      => $row->role,
                        'title'     => $title,
                        'admin_url' => $this->adminEditUrlFor($pageType, $pageId),
                        'via'       => [
                            'fileable_type' => $row->fileable_type,
                            'fileable_id'   => $row->fileable_id,
                        ],
                    ];
                })
                    ->unique(fn($p) => ($p['type'] ?? '').'#'.($p['id'] ?? '').'|'.($p['role'] ?? ''))
                    ->values();

                return [
                    'file_id'  => (int) $fileId,
                    'filename' => (string) ($fileMeta[$fileId] ?? ''),
                    'places'   => $places,
                ];
            })->values();

            return response()->json([
                'message' => "This folder (or its subfolders) contains used files. Remove usages first.",
                'used'    => $usedReport,
            ], 422);
        }

        // No usages: delete all (unused) files (soft-delete ok)
        File::whereIn('id', $fileIds)->get()->each->delete();

        // Finally delete subfolders + the folder itself
        LibraryFolder::whereIn('id', $ids)->delete();

        return response()->json([
            'ok' => true,
            'deleted' => [
                'files'   => $fileIds->count(),
                'folders' => $ids->count(),
            ],
        ]);
    }

    private function adminEditUrlFor(?string $modelClass, ?int $id): ?string
    {
        if (!$modelClass || !$id || !class_exists($modelClass)) return null;

        try {
            $model = app($modelClass);
            if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                $module = $model->getTable();
            } else {
                $module = Str::snake(Str::pluralStudly(class_basename($modelClass)));
            }
        } catch (\Throwable $e) {
            $module = Str::snake(Str::pluralStudly(class_basename($modelClass)));
        }

        $routeName = "admin.$module.edit";
        if (app('router')->has($routeName)) {
            try {
                return route($routeName, $id);
            } catch (\Throwable $e) {}
        }

        return url("/admin/{$module}/{$id}/edit");
    }

    /**
     * Lifted from MediaFolderController, adapted name-wise.
     * Resolves a fileable to its owning page if the file is attached via a Block.
     */
    private function resolveUsageToPage(?string $fileableType, int $fileableId): array
    {
        $isBlockType = function ($type) {
            return in_array($type, ['blocks', Block::class, 'A17\\Twill\\Models\\Block'], true);
        };

        if ($isBlockType($fileableType)) {
            $visited = [];
            $currentId = $fileableId;

            while ($currentId && !in_array($currentId, $visited, true)) {
                $visited[] = $currentId;

                $block = Block::query()->find($currentId);
                if (!$block) {
                    $block = DB::table('twill_blocks')->where('id', $currentId)->first();
                    if (!$block) break;
                    $blockableType = $block->blockable_type;
                    $blockableId   = (int)$block->blockable_id;
                } else {
                    $blockableType = $block->blockable_type;
                    $blockableId   = (int)$block->blockable_id;
                }

                if ($isBlockType($blockableType)) {
                    $currentId = $blockableId; // parent block
                    continue;
                }

                return [$blockableType, $blockableId];
            }

            return [$fileableType, $fileableId];
        }

        return [$fileableType, $fileableId];
    }

    private function buildTreeFromRows($rows): array
    {
        $nodes = [];
        foreach ($rows as $r) {
            $nodes[$r->id] = [
                'id'       => $r->id,
                'name'     => $r->name,
                'path'     => $r->path,
                'children' => [],
            ];
        }

        $root = ['id' => null, 'name' => '', 'path' => '', 'children' => []];

        foreach ($rows as $r) {
            if ($r->parent_id && isset($nodes[$r->parent_id])) {
                $nodes[$r->parent_id]['children'][] = &$nodes[$r->id];
            } else {
                $root['children'][] = &$nodes[$r->id];
            }
        }

        return $root;
    }
}
