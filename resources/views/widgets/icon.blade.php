{{-- Icon widget render — <i class="..."></i>, optionally wrapped in a link.
     Size maps to a CSS font-size; class string is library-agnostic. --}}

@php
    $__class = (string) $section->contentField('class');
    $__url = (string) $section->contentField('url');
    $__sizes = [
        'sm' => '16px',
        'md' => '24px',
        'lg' => '32px',
        'xl' => '48px',
        '2xl' => '64px',
    ];
    $__size = $__sizes[$section->contentField('size')] ?? $__sizes['md'];
@endphp

<div class="vb-widget vb-widget-icon">
    @if($__class)
        @if($__url)
            <a href="{{ $__url }}"><i class="{{ $__class }}" style="font-size: {{ $__size }};" aria-hidden="true"></i></a>
        @else
            <i class="{{ $__class }}" style="font-size: {{ $__size }};" aria-hidden="true"></i>
        @endif
    @endif
</div>
