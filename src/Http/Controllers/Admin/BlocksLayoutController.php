<?php

namespace A17\Twill\Http\Controllers\Admin;

use Illuminate\Http\Request;
use A17\Twill\Models\Block;

class BlocksLayoutController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'editorName' => ['required', 'string'],
            'layout' => ['required', 'array'],
            'layout.*.id' => ['required', 'integer'],
            'layout.*.grid' => ['required', 'array'],
        ]);

        $byId = collect($data['layout'])->keyBy('id');

        Block::whereIn('id', $byId->keys())->get()->each(function (Block $block) use ($byId) {
            $content = $block->content ?? [];
            $content['grid'] = $byId[$block->id]['grid']; // {x,y,w,h}
            $block->content = $content;
            $block->save();
        });

        return response()->json(['ok' => true]);
    }
}
