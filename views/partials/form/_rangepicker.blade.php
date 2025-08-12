<div>
    <label>{{ $label }}</label>
    <a17-rangepicker
        {!! $formFieldName() !!}
        :min="{{ $minValue }}"
        :max="{{ $maxValue }}"
        :step="{{ $stepValue }}"
        :value="{{ old($name, $default ?? $minValue) }}"
        :disabled="{{ $disabled ? 'true' : 'false' }}"
        @if ($required) :required="true" @endif
        @if ($note) note="{{ $note }}" @endif
        in-store="value"
        @input="currentValue = $event"
    ></a17-rangepicker>
    <div>Current: @{{ currentValue }}</div>
</div>

@unless($renderForBlocks || $renderForModal || (!isset($item->$name) && is_null($formFieldsValue = getFormFieldsValue($form_fields, $name, $default))))
@push('vuexStore')
    @include('twill::partials.form.utils._selector_input_store')
@endpush
@endunless
