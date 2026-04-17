{{-- Image widget render — <img> or <a><img></a> depending on `url`.
     Src is a storage path / asset / absolute URL; resolved through the
     host's MediaServiceInterface helper.  --}}

@php
    $__src = $section->contentField('src');
    $__alt = $section->contentField('alt');
    $__url = $section->contentField('url');
    $__allowedFit = ['cover','contain','fill','none'];
    $__fit = in_array($section->contentField('fit'), $__allowedFit, true)
        ? $section->contentField('fit')
        : 'cover';

    // Resolve src to a public URL. Absolute URL → verbatim; "assets/"
    // prefix → prepended with "/"; anything else goes through /storage/.
    $__resolved = '';
    if (is_string($__src) && $__src !== '') {
        if (preg_match('#^(https?:)?//#', $__src)) {
            $__resolved = $__src;
        } elseif (str_starts_with($__src, 'assets/')) {
            $__resolved = '/'.$__src;
        } else {
            $__resolved = '/storage/'.$__src;
        }
    }
@endphp

<div class="vb-widget vb-widget-image">
    @if($__resolved)
        @if(! empty($__url))
            <a href="{{ $__url }}"><img
                src="{{ $__resolved }}"
                alt="{{ $__alt ?: '' }}"
                style="object-fit: {{ $__fit }}; max-width: 100%; height: auto;"
                loading="lazy"
            ></a>
        @else
            <img
                src="{{ $__resolved }}"
                alt="{{ $__alt ?: '' }}"
                style="object-fit: {{ $__fit }}; max-width: 100%; height: auto;"
                loading="lazy"
            >
        @endif
    @else
        <div style="padding: 24px; background: #f3f4f6; color: #9ca3af; text-align: center; border-radius: 4px;">
            (no image)
        </div>
    @endif
</div>
