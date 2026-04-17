{{-- Button widget render — <a> styled as a button.
     Variant / size classes are neutral; host apps can restyle via CSS. --}}

@php
    $__label = $section->contentField('label');
    $__url = $section->contentField('url') ?: '#';
    $__newTab = (bool) $section->contentField('new_tab');
    $__variant = in_array($section->contentField('variant'), ['primary','secondary','outline','link'], true)
        ? $section->contentField('variant')
        : 'primary';
    $__size = in_array($section->contentField('size'), ['sm','md','lg'], true)
        ? $section->contentField('size')
        : 'md';
@endphp

<a
    href="{{ $__url }}"
    @if($__newTab) target="_blank" rel="noopener" @endif
    class="vb-widget vb-widget-button vb-btn-{{ $__variant }} vb-btn-size-{{ $__size }}"
    data-vb-editable
    data-vb-field="label"
    data-vb-section-id="{{ $section->id }}"
    data-vb-locale="{{ app()->getLocale() }}"
>{{ $__label }}</a>
