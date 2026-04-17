{{-- Divider widget render — horizontal rule with configured style.
     All three content fields are SelectField-bound so values are
     whitelist-validated by the field before save; we still clamp on
     render as a defence-in-depth. --}}

@php
    $__allowedStyles = ['solid','dashed','dotted','double'];
    $__allowedThick = ['1px','2px','4px','8px'];
    $__allowedWidth = ['100%','75%','50%','25%'];

    $__style = in_array($section->contentField('line_style'), $__allowedStyles, true)
        ? $section->contentField('line_style')
        : 'solid';
    $__thick = in_array($section->contentField('thickness'), $__allowedThick, true)
        ? $section->contentField('thickness')
        : '1px';
    $__width = in_array($section->contentField('width'), $__allowedWidth, true)
        ? $section->contentField('width')
        : '100%';
@endphp

<div class="vb-widget vb-widget-divider">
    <hr style="border: 0; border-top: {{ $__thick }} {{ $__style }} currentColor; width: {{ $__width }}; margin-left: auto; margin-right: auto;">
</div>
