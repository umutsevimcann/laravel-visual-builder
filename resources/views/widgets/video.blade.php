{{-- Video widget render — <video> for direct files, <iframe> for providers.
     YouTube / Vimeo URL normalization: accepts any standard page URL and
     rewrites to the provider's embed format with autoplay / loop / mute
     flags appended from the widget's toggles. --}}

@php
    $__url = (string) $section->contentField('url');
    $__provider = in_array($section->contentField('provider'), ['file','youtube','vimeo'], true)
        ? $section->contentField('provider')
        : 'file';
    $__autoplay = (bool) $section->contentField('autoplay');
    $__loop = (bool) $section->contentField('loop');
    $__controls = $section->contentField('controls') === null
        ? true
        : (bool) $section->contentField('controls');
    $__allowedRatios = ['16/9','4/3','1/1','21/9'];
    $__ratio = in_array($section->contentField('aspect_ratio'), $__allowedRatios, true)
        ? $section->contentField('aspect_ratio')
        : '16/9';

    // Build provider-specific embed URL. Defensive: any URL we don't
    // recognise falls back to empty so no arbitrary iframe src is emitted.
    $__embedUrl = '';
    if ($__provider === 'youtube' && $__url !== '') {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})#', $__url, $m)) {
            $__flags = [];
            if ($__autoplay) $__flags[] = 'autoplay=1';
            if ($__autoplay) $__flags[] = 'mute=1';
            if ($__loop) $__flags[] = 'loop=1&playlist='.$m[1];
            if (! $__controls) $__flags[] = 'controls=0';
            $__embedUrl = 'https://www.youtube-nocookie.com/embed/'.$m[1].($__flags ? '?'.implode('&', $__flags) : '');
        }
    } elseif ($__provider === 'vimeo' && $__url !== '') {
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $__url, $m)) {
            $__flags = [];
            if ($__autoplay) $__flags[] = 'autoplay=1';
            if ($__autoplay) $__flags[] = 'muted=1';
            if ($__loop) $__flags[] = 'loop=1';
            if (! $__controls) $__flags[] = 'controls=0';
            $__embedUrl = 'https://player.vimeo.com/video/'.$m[1].($__flags ? '?'.implode('&', $__flags) : '');
        }
    }
@endphp

<div class="vb-widget vb-widget-video" style="aspect-ratio: {{ $__ratio }}; max-width: 100%;">
    @if($__provider === 'file' && $__url !== '')
        <video
            src="{{ $__url }}"
            @if($__controls) controls @endif
            @if($__autoplay) autoplay muted playsinline @endif
            @if($__loop) loop @endif
            style="width: 100%; height: 100%; object-fit: cover;"
        ></video>
    @elseif($__embedUrl)
        <iframe
            src="{{ $__embedUrl }}"
            frameborder="0"
            allow="autoplay; encrypted-media; picture-in-picture"
            allowfullscreen
            style="width: 100%; height: 100%;"
        ></iframe>
    @else
        <div style="padding: 24px; background: #f3f4f6; color: #9ca3af; text-align: center; border-radius: 4px;">
            (no video URL set)
        </div>
    @endif
</div>
