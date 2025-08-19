<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FileFolderController extends Controller
{
    public function index(Request $request)
    {
        $explicit = LibraryFolder::query()->where('library', 'file')->get(['path'])->pluck('path')->all();

        $fromFiles = DB::table('twill_files')
            ->whereNotNull('folder_path')
            ->where('folder_path', '!=', '')
            ->distinct()
            ->pluck('folder_path')
            ->all();

        $paths = collect($explicit)->merge($fromFiles)->unique()->values()->all();

        return response()->json([
            'tree' => $this->buildTree($paths),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'parent' => 'nullable|string',
            'name' => 'required|string|max:255',
        ]);

        $parent = $this->normalize($data['parent'] ?? '');
        $name = $this->sanitizeName($data['name']);
        $path = trim($parent . '/' . $name, '/');

        if ($path === '') return response()->json(['message' => 'Invalid folder name'], 422);

        LibraryFolder::firstOrCreate([
            'path' => $path,
        ], [
            'library' => 'file',
            'name' => $name,
            'parent_id' => optional(LibraryFolder::where('path', $parent)->first())->id,
        ]);

        return response()->json(['ok' => true], 201);
    }

    // Rename folder
    public function update(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'file', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldPath = $folder->path;
        $newPath = trim(($folder->parent?->path ?? '') . '/' . $data['name'], '/');

        // Ensure uniqueness
        if (LibraryFolder::where('library', 'file')->where('path', $newPath)->where('id', '!=', $folder->id)->exists()) {
            return response()->json(['message' => 'A folder with that name already exists here.'], 422);
        }

        DB::transaction(function () use ($folder, $data, $oldPath, $newPath) {
            // 1) Update this folder
            $folder->update(['name' => $data['name'], 'path' => $newPath]);

            // 2) Cascade paths to descendants
            $like = $oldPath === '' ? '%' : $oldPath . '/%';
            $descendants = LibraryFolder::where('library', 'file')
                ->where('path', 'like', $like)
                ->get();

            foreach ($descendants as $child) {
                $child->update([
                    'path' => preg_replace(
                        '#^' . preg_quote($oldPath, '#') . '#',
                        $newPath,
                        $child->path
                    )
                ]);
            }
        });

        return response()->json(['ok' => true, 'folder' => $folder->fresh()]);
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'target' => 'nullable|string',
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ]);

        $target = $this->normalize($data['target'] ?? '');

        DB::table('twill_files')->whereIn('id', $data['mediaIds'])
            ->update(['folder_path' => $target]);

        return response()->json(['ok' => true]);
    }

    private function normalize(?string $path): string
    {
        $path = trim($path ?? '', '/');
        // reject unsafe segments
        if (Str::contains($path, ['..', "\0"])) return '';
        // collapse duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        // strip slashes and control chars
        $name = preg_replace('#[\/\0]#', '', $name);
        // optional: restrict to a safe charset
        return $name;
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

        // Prevent moving into self/descendant
        $targetPath = trim($target->path ?? '', '/');     // '' means root
        $sourcePath = trim($source->path ?? '', '/');
        if ($targetPath !== '' && ($targetPath === $sourcePath || str_starts_with($targetPath . '/', $sourcePath . '/'))) {
            return response()->json(['message' => 'Cannot move a folder into itself or its descendant.'], 422);
        }

        // New path (keep same name)
        $newPath = trim(($targetPath ? $targetPath . '/' : '') . $source->name, '/');

        // Ensure uniqueness at destination
        $exists = LibraryFolder::where('library', 'file')
            ->where('path', $newPath)
            ->where('id', '!=', $source->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'A folder with that name already exists in the destination.'], 422);
        }

        $oldPath = $source->path;

        DB::transaction(function () use ($source, $data, $oldPath, $newPath, $target) {
            // Move source
            $source->update([
                'parent_id' => $target?->id,
                'path'      => $newPath,
            ]);

            // Cascade descendants
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
        // Library flag is 'file' (adjust to 'files' if your data uses that)
        abort_unless($folder->library === 'file', 404);

        // Collect this folder + its descendants
        $ids = collect([$folder->id])
            ->merge(
                LibraryFolder::where('library', 'file')
                    ->where('path', 'like', $folder->path . '/%')
                    ->pluck('id')
            )
            ->unique()
            ->values();

        // All files (directly) in these folders
        $fileIds = DB::table('twill_files')
            ->whereIn('folder_id', $ids)
            ->pluck('id');

        // Short-circuit if no files at all
        if ($fileIds->isEmpty()) {
            LibraryFolder::whereIn('id', $ids)->delete();

            return response()->json([
                'ok' => true,
                'deleted' => ['files' => 0, 'folders' => $ids->count()],
            ]);
        }

        // Any usages?
        $usages = DB::table('twill_fileables')
            ->whereIn('file_id', $fileIds)
            ->select('file_id', 'fileable_type', 'fileable_id', 'role')
            ->orderBy('file_id')
            ->get();

        $usedCount = $usages->count();

        if ($usedCount > 0) {
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
                        } catch (\Throwable $e) { /* ignore */
                        }
                    }

                    return [
                        'type' => $pageType ?: $row->fileable_type,
                        'id' => $pageId ?: $row->fileable_id,
                        'role' => $row->role,
                        'title' => $title,
                        'admin_url' => $this->adminEditUrlFor($pageType, $pageId),
                        'via' => [
                            'fileable_type' => $row->fileable_type,
                            'fileable_id' => $row->fileable_id,
                        ],
                    ];
                })
                    ->unique(fn($p) => ($p['type'] ?? '') . '#' . ($p['id'] ?? '') . '|' . ($p['role'] ?? ''))
                    ->values();

                return [
                    'file_id' => (int)$fileId,
                    'filename' => (string)($fileMeta[$fileId] ?? ''),
                    'places' => $places,
                ];
            })->values();

            return response()->json([
                'message' => "This folder (or its subfolders) contains used files. Remove usages first.",
                'used' => $usedReport,
            ], 422);
        }

        // No usages: delete all (unused) files in these folders (soft-delete ok)
        File::whereIn('id', $fileIds)->get()->each->delete();

        // Finally delete subfolders + the folder itself
        LibraryFolder::whereIn('id', $ids)->delete();

        return response()->json([
            'ok' => true,
            'deleted' => [
                'files' => $fileIds->count(),
                'folders' => $ids->count(),
            ],
        ]);
    }


    private function buildTree(array $paths): array
    {
        $root = ['name' => '', 'children' => []];

        foreach ($paths as $p) {
            $segments = array_values(array_filter(explode('/', $p), 'strlen'));
            $node = &$root;
            foreach ($segments as $seg) {
                if (!isset($node['children'])) $node['children'] = [];
                $idx = null;
                foreach ($node['children'] as $k => $child) {
                    if ($child['name'] === $seg) {
                        $idx = $k;
                        break;
                    }
                }
                if ($idx === null) {
                    $node['children'][] = ['name' => $seg, 'children' => []];
                    $idx = array_key_last($node['children']);
                }
                $node = &$node['children'][$idx];
            }
            unset($node);
        }

        return $root;
    }
}
