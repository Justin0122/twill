<div>
    <label>{{ $label }}</label>
    <a17-rangepicker
        {!! $formFieldName() !!}
        :min="{{ $minValue }}"
        :max="{{ $maxValue }}"
        :step="{{ $stepValue }}"
        v-model="formFields['{{ $name }}']"
        :disabled="{{ $disabled ? 'true' : 'false' }}"
        @if ($required) :required="true" @endif
        @if ($note) note="{{ $note }}" @endif
        in-store="value"
    ></a17-rangepicker>
    <div>Current: @{{ formFields['{{ $name }}'] }}</div>
</div>

@unless($renderForBlocks || $renderForModal || (!isset($item->$name) && is_null($formFieldsValue = getFormFieldsValue($form_fields, $name, $default))))
    @push('vuexStore')
        @include('twill::partials.form.utils._selector_input_store')
    @endpush
@endunless
