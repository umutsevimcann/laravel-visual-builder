{{-- Spacer widget render — empty block with configured min-height.
     Height is stored as one of the SelectField preset strings (e.g. "40px").
     Validated in the widget to be one of those presets. --}}

@php
    $__allowed = ['10px','20px','40px','80px','120px','200px'];
    $__height = in_array($section->contentField('height'), $__allowed, true)
        ? $section->contentField('height')
        : '40px';
@endphp

<div
    class="vb-widget vb-widget-spacer"
    style="min-height: {{ $__height }}"
    aria-hidden="true"
></div>
