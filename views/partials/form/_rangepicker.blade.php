<a17-rangepicker
    label="{{ $label }}"
    {!! $formFieldName() !!}
    :min="{{ $min ?? 0 }}"
    :max="{{ $max ?? 100 }}"
    :step="{{ $step ?? 1 }}"
    :value="{{ old($name, $default ?? 0) }}"
    :disabled="{{ $disabled ? 'true' : 'false' }}"
    @if ($required) :required="true" @endif
    @if ($note) note="{{ $note }}" @endif
    in-store="value"
></a17-rangepicker>

@unless($renderForBlocks || $renderForModal || (!isset($item->$name) && is_null($formFieldsValue = getFormFieldsValue($form_fields, $name, $default))))
@push('vuexStore')
    @include('twill::partials.form.utils._selector_input_store')
@endpush
@endunless
