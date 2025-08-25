<?php

namespace App\Http\Controllers\Twill;

use A17\Twill\Http\Controllers\Admin\ModuleController as BaseModuleController;
use A17\Twill\Models\Block;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Services\Forms\Fields\BlockEditor;
use A17\Twill\Services\Forms\Fields\Input;
use A17\Twill\Services\Forms\Fields\Medias;
use A17\Twill\Services\Forms\Form;
use A17\Twill\Services\Listings\Columns\Text;
use A17\Twill\Services\Listings\TableColumns;

class PageController extends BaseModuleController
{
    protected $moduleName = 'pages';

    /**
     * This method can be used to enable/disable defaults. See setUpController in the docs for available options.
     */
    protected function setUpController(): void
    {
        $this->setPermalinkBase('');
        $this->withoutLanguageInPermalink();
    }

    /**
     * See the table builder docs for more information. If you remove this method you can use the blade files.
     * When using twill:module:make you can specify --bladeForm to use a blade form instead.
     */
    public function getForm(TwillModelContract $model): Form
    {
        $form = parent::getForm($model);

        $form->add(
            Input::make()->name('description')->label('Description')->translatable()
        );

        $form->add(
            Medias::make()->name('cover')->label('Cover image')
        );

        $form->add(
            BlockEditor::make()
        );

        return $form;
    }

    /**
     * This is an example and can be removed if no modifications are needed to the table.
     */
    protected function additionalIndexTableColumns(): TableColumns
    {
        $table = parent::additionalIndexTableColumns();

        $table->add(
            Text::make()->field('description')->title('Description')
        );

        return $table;
    }

    public function afterSave($object, $request)
    {
        $layouts = $request->input('blocks_layout', []); // array: editorName => json/string

        foreach ($layouts as $editorName => $layout) {
            if (is_string($layout)) {
                $layout = json_decode($layout, true) ?: [];
            }
            if (! is_array($layout)) {
                continue;
            }

            $byId = collect($layout)->keyBy('id');

            Block::where('blockable_type', $object->getMorphClass())
                ->where('blockable_id', $object->id)
                ->where('editor_name', $editorName)
                ->get()
                ->each(function (Block $block) use ($byId) {
                    if (! $byId->has((array) $block->id)) {
                        return;
                    }
                    $content = $block->content ?? [];
                    $content['grid'] = $byId[$block->id]['grid'] ?? null; // ['x','y','w','h']
                    $block->content = $content;
                    $block->save();
                });
        }
    }
}
