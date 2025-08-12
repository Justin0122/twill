<?php

namespace A17\Twill\View\Components\Fields;

use Illuminate\Contracts\View\View;

class RangePicker extends Field
{
    public function __construct(
        string $name,
        string $label,
        int|float $min = 0,
        int|float $max = 100,
        int|float $step = 1,
        int|float|null $default = null,
        bool $required = false,
        bool $disabled = false,
        bool $readOnly = false,
        ?string $note = '',
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            required: $required,
            note: $note,
            default: $default,
            disabled: $disabled,
            readOnly: $readOnly,
        );

        $this->withMeta([
            'min' => $min,
            'max' => $max,
            'step' => $step,
        ]);
    }

    public function render(): View
    {
        return view('twill::partials.form._rangepicker', $this->data());
    }
}
