<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Helpers\BlockRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Cache;

class BlocksController extends Controller
{
    /**
     * Render an HTML preview of a single block.
     * This is used by the full screen content editor.
     */
    public function preview(
        Application $app,
        ViewFactory $viewFactory,
        Request $request,
    ): string {
        if ($request->has('activeLanguage')) {
            $app->setLocale($request->get('activeLanguage'));
        }

        $data = $request->except('activeLanguage');
        $mapping = config('twill.block_editor.block_views_mappings');
        $layout = config('twill.block_editor.block_single_layout');
        $cacheKey = 'block_preview_' . md5(json_encode($data));

        $content = Cache::remember($cacheKey, 720, function () use ($data, $mapping) {
            return BlockRenderer::fromCmsArray($data)->render($mapping, []);
        });

        if ($viewFactory->exists($layout)) {
            $viewFactory->inject('content', $content);
            $result = view($layout);
        } else {
            $result = view('twill::errors.block_layout', ['view' => $layout]);
        }

        return html_entity_decode($result->render());
    }
}
