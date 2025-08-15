<?php
namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaFolderController extends Controller
{
    public function index(Request $request)
    {
        $explicit = LibraryFolder::where('library', 'media')->pluck('path')->all();
        $fromMedias = DB::table('twill_medias')
            ->whereNotNull('folder_path')->where('folder_path', '!=', '')
            ->distinct()->pluck('folder_path')->all();

        return response()->json(['tree' => $this->buildTree(array_unique(array_merge($explicit, $fromMedias)))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:image,video,file,media', // you can narrow this to your types
            'parent' => 'nullable|string',
            'name' => 'required|string|max:255',
        ]);

        $parent = trim(($data['parent'] ?? ''), '/');
        $name   = trim($data['name'], '/');
        $path   = trim($parent . '/' . $name, '/');

        LibraryFolder::firstOrCreate(['path' => $path], [
            'library' => 'media',
            'name' => $name,
            'parent_id' => optional(LibraryFolder::where('path', $parent)->first())->id,
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'target' => 'nullable|string',
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ]);

        $target = trim($data['target'] ?? '', '/');

        DB::table('twill_medias')->whereIn('id', $data['mediaIds'])
            ->update(['folder_path' => $target]);

        return response()->json(['ok' => true]);
    }

    private function buildTree(array $paths): array
    {
        $root = ['name' => '', 'children' => []];
        foreach ($paths as $p) {
            $node = &$root;
            foreach (array_filter(explode('/', $p), 'strlen') as $seg) {
                $idx = null;
                foreach ($node['children'] ?? [] as $k => $child) {
                    if ($child['name'] === $seg) { $idx = $k; break; }
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
