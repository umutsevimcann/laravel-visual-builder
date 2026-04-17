{{-- Heading widget render — <h{level}> with inline-editable text.
     Level comes from the `level` content field (h1..h6 or div).
     Text is translatable; display via getTranslation / contentField. --}}

@php
    $__level = in_array($section->contentField('level'), ['h1','h2','h3','h4','h5','h6','div'], true)
        ? $section->contentField('level')
        : 'h2';
    $__text = $section->contentField('text');
@endphp

<{{ $__level }}
    class="vb-widget vb-widget-heading"
    data-vb-editable
    data-vb-field="text"
    data-vb-section-id="{{ $section->id }}"
    data-vb-locale="{{ app()->getLocale() }}"
>{{ $__text }}</{{ $__level }}>
