@php
    $colClassAttr = (isset($middle) || isset($middleFields)) ? 'col--third col--third-wrap' : '';
    $colStyle = 'flex: 1 1 0; min-width: 0; box-sizing: border-box; vertical-align: top;';
@endphp
<div class="wrapper"
     style="display: flex; gap: 0.6rem; margin-left: 0; white-space: normal; width: 100%;">
    <div class="{{ $colClassAttr }}" style="{{ $colStyle }}">
        @isset($leftFields)
            @foreach($leftFields as $field)
                {!! $field->render() !!}
            @endforeach
        @endisset
        {{ $left }}
    </div>
    @if(isset($middle) || isset($middleFields))
        <div class="{{ $colClassAttr }}" style="{{ $colStyle }}">
            @isset($middleFields)
                @foreach($middleFields as $field)
                    {!! $field->render() !!}
                @endforeach
            @endisset
            {{ $middle }}
        </div>
    @endif
    <div class="{{ $colClassAttr }}" style="{{ $colStyle }}">
        @isset($rightFields)
            @foreach($rightFields as $field)
                {!! $field->render() !!}
            @endforeach
        @endisset
        {{ $right }}
    </div>
</div>
