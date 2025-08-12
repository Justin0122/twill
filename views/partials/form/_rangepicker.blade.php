<a17-rangepicker
    label="{{ $label }}"
    :min="{{ $min ?? 0 }}"
    :max="{{ $max ?? 100 }}"
    :step="{{ $step ?? 1 }}"
    default-value="{{ $default ?? 0 }}"
    {!! $formFieldName() !!}
    in-store="value"
></a17-rangepicker>

@unless($renderForBlocks || $renderForModal || (!isset($item->$name) && is_null($formFieldsValue = getFormFieldsValue($form_fields, $name, $default))))
    @push('vuexStore')
        window['{{ config('twill.js_namespace') }}'].STORE.form.fields.push({
        name: '{{ $name }}',
        value: {!! json_encode($item->$name ?? $formFieldsValue ?? $default) !!}
        })
    @endpush
@endunless
