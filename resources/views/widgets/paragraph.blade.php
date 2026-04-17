{{-- Paragraph widget render — sanitized rich-text body.
     Body is stored as translatable HTML; purified through the configured
     SanitizerInterface on save, so {!! !!} here is safe. --}}

@php
    $__body = $section->contentField('body');
@endphp

<div
    class="vb-widget vb-widget-paragraph"
    data-vb-editable
    data-vb-html
    data-vb-field="body"
    data-vb-section-id="{{ $section->id }}"
    data-vb-locale="{{ app()->getLocale() }}"
>{!! $__body !!}</div>
