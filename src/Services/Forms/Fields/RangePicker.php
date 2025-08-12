<?php

namespace A17\Twill\Services\Forms\Fields;

use A17\Twill\Services\Forms\Fields\Traits\HasMax;
use A17\Twill\Services\Forms\Fields\Traits\HasMin;
use A17\Twill\Services\Forms\Fields\Traits\IsTranslatable;

class RangePicker extends BaseFormField
{
    use IsTranslatable;
    use HasMax;
    use HasMin;

    public static function make(): static
    {
        return new self(
            component: \A17\Twill\View\Components\Fields\RangePicker::class,
            mandatoryProperties: ['name', 'label']
        );
    }
}
