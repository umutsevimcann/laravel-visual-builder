{{-- IconBox widget render — icon + title + body, with layout switcher.
     Layout maps to flex-direction so the icon sits above / left of / right
     of the text block. Body is purified on save (HtmlField), safe to !!. --}}

@php
    $__iconClass = (string) $section->contentField('icon_class');
    $__title = (string) $section->contentField('title');
    $__body = $section->contentField('body');
    $__allowedLayouts = ['top', 'left', 'right'];
    $__layout = in_array($section->contentField('layout'), $__allowedLayouts, true)
        ? $section->contentField('layout')
        : 'top';

    $__flexDir = [
        'top' => 'column',
        'left' => 'row',
        'right' => 'row-reverse',
    ][$__layout];
    $__align = $__layout === 'top' ? 'center' : 'flex-start';
@endphp

<div
    class="vb-widget vb-widget-icon-box"
    style="display: flex; flex-direction: {{ $__flexDir }}; align-items: {{ $__align }}; gap: 16px;"
>
    @if($__iconClass)
        <i class="{{ $__iconClass }}" aria-hidden="true" style="font-size: 32px;"></i>
    @endif

    <div class="vb-widget-icon-box-content" style="flex: 1;">
        @if($__title)
            <h3
                data-vb-editable
                data-vb-field="title"
                data-vb-section-id="{{ $section->id }}"
                data-vb-locale="{{ app()->getLocale() }}"
            >{{ $__title }}</h3>
        @endif

        @if($__body)
            <div
                data-vb-editable
                data-vb-html
                data-vb-field="body"
                data-vb-section-id="{{ $section->id }}"
                data-vb-locale="{{ app()->getLocale() }}"
            >{!! $__body !!}</div>
        @endif
    </div>
</div>
