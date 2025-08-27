@php
    $colStyle = (isset($middle) || isset($middleFields)) ? '' : 'width: 49%; box-sizing: border-box; display: inline-block; vertical-align: top;';
    $colClassAttr = (isset($middle) || isset($middleFields)) ? 'col--third col--third-wrap' : '';
@endphp
<div class="wrapper" style="margin-left: 0; white-space: nowrap; gap:0.6rem;">
    <div class="{{ $colClassAttr }}" @if($colStyle)style="{{ $colStyle }}"@endif>
        @isset($leftFields)
            @foreach($leftFields as $field)
                {!! $field->render() !!}
            @endforeach
        @endisset
        {{ $left }}
    </div>
    @if(isset($middle) || isset($middleFields))
        <div class="{{ $colClassAttr }}">
            @isset($middleFields)
                @foreach($middleFields as $field)
                    {!! $field->render() !!}
                @endforeach
            @endisset
            {{ $middle }}
        </div>
    @endif
    <div class="{{ $colClassAttr }}" @if($colStyle)style="{{ $colStyle }}"@endif>
        @isset($rightFields)
            @foreach($rightFields as $field)
                {!! $field->render() !!}
            @endforeach
        @endisset
        {{ $right }}
    </div>
</div>
