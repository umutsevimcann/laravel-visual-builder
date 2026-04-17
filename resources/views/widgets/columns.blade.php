{{-- Columns widget render — CSS grid with one slot per column_index.
     Children are loaded through BuilderSection::children() (ordered by
     column_index then sort_order) and routed to their own widget view
     partials via SectionTypeRegistry::findOrFail()->viewPartial().

     Stack behaviour uses a single `@media` query so there is no JS
     dependency; the stack_on select maps to one of the three breakpoints
     the package exposes elsewhere. --}}

@php
    // Map the word-keyed select values back to their numeric CSS values.
    // Words are stored to sidestep PHP's auto-cast of purely-numeric string
    // keys to int; see ColumnsWidget::fields() for the rationale.
    $__countMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6];
    $__gapMap = ['none' => '0', 'tight' => '8px', 'small' => '16px', 'medium' => '24px', 'large' => '48px', 'wide' => '80px'];

    $__count = $__countMap[$section->contentField('count')] ?? 2;
    $__gap = $__gapMap[$section->contentField('gap')] ?? '24px';

    $__stack = in_array($section->contentField('stack_on'), ['mobile','tablet','never'], true)
        ? $section->contentField('stack_on')
        : 'mobile';

    // Children are eager-loaded once and grouped by column_index so the
    // render loop below runs O(n) and not O(n * columns).
    $__childrenByCol = $section->orderedChildren()->groupBy(
        static fn ($child) => (int) ($child->column_index ?? 0)
    );

    $__registry = app(\Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry::class);

    // Grid gap + wrapping — stack_on='never' strips the @media query
    // entirely so no wrap ever occurs.
    $__uniqueClass = 'vb-widget-columns-'.$section->id;
    $__stackBp = ['mobile' => 767, 'tablet' => 1023];
@endphp

@push('styles')
    <style>
        .{{ $__uniqueClass }} {
            display: grid;
            grid-template-columns: repeat({{ $__count }}, 1fr);
            gap: {{ $__gap }};
        }
        @if(isset($__stackBp[$__stack]))
        @media (max-width: {{ $__stackBp[$__stack] }}px) {
            .{{ $__uniqueClass }} {
                grid-template-columns: 1fr;
            }
        }
        @endif
    </style>
@endpush

<div class="vb-widget vb-widget-columns {{ $__uniqueClass }}">
    @for($__i = 0; $__i < $__count; $__i++)
        <div class="vb-widget-column" data-vb-column-index="{{ $__i }}">
            @foreach($__childrenByCol->get($__i, collect()) as $__child)
                @php
                    $__childType = $__registry->find($__child->type);
                @endphp
                @if($__childType)
                    @include($__childType->viewPartial(), [
                        'section' => $__child,
                        'references' => $references ?? [],
                    ])
                @endif
            @endforeach
        </div>
    @endfor
</div>
