<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Services\BreakpointStyleResolver;

beforeEach(function (): void {
    // Default Bootstrap-5-style thresholds. Override per test when verifying
    // the tablet_max/mobile_max plumbing.
    $this->resolver = new BreakpointStyleResolver(tabletMaxPx: 1023, mobileMaxPx: 767);
});

describe('resolve() — scalar / object shape handling', function (): void {
    it('returns scalar values unchanged for every breakpoint', function (): void {
        $style = ['bg_color' => '#ff0000', 'alignment' => 'center'];

        expect($this->resolver->resolve($style, 'desktop'))
            ->toBe(['bg_color' => '#ff0000', 'alignment' => 'center']);
        expect($this->resolver->resolve($style, 'tablet'))
            ->toBe(['bg_color' => '#ff0000', 'alignment' => 'center']);
        expect($this->resolver->resolve($style, 'mobile'))
            ->toBe(['bg_color' => '#ff0000', 'alignment' => 'center']);
    });

    it('picks the exact breakpoint slice when present', function (): void {
        $style = [
            'padding_y' => ['desktop' => '80px', 'tablet' => '60px', 'mobile' => '40px'],
        ];

        expect($this->resolver->resolve($style, 'desktop')['padding_y'])->toBe('80px');
        expect($this->resolver->resolve($style, 'tablet')['padding_y'])->toBe('60px');
        expect($this->resolver->resolve($style, 'mobile')['padding_y'])->toBe('40px');
    });

    it('omits properties whose resolved value is empty or null', function (): void {
        $style = [
            'padding_y' => '',
            'alignment' => null,
            'bg_color' => '#000',
        ];

        expect($this->resolver->resolve($style, 'desktop'))
            ->toBe(['bg_color' => '#000']);
    });

    it('treats an unsupported value shape as "no value" without throwing', function (): void {
        $style = ['padding_y' => (object) ['not' => 'an array']];

        expect($this->resolver->resolve($style, 'desktop'))->toBe([]);
    });
});

describe('resolve() — inheritance rules', function (): void {
    it('mobile inherits from tablet when mobile is missing', function (): void {
        $style = ['padding_y' => ['desktop' => '80px', 'tablet' => '60px']];

        expect($this->resolver->resolve($style, 'mobile')['padding_y'])->toBe('60px');
    });

    it('mobile inherits from desktop when both tablet and mobile are missing', function (): void {
        $style = ['padding_y' => ['desktop' => '80px']];

        expect($this->resolver->resolve($style, 'mobile')['padding_y'])->toBe('80px');
    });

    it('tablet inherits from desktop when tablet is missing', function (): void {
        $style = ['padding_y' => ['desktop' => '80px', 'mobile' => '40px']];

        expect($this->resolver->resolve($style, 'tablet')['padding_y'])->toBe('80px');
    });

    it('desktop falls back to tablet when desktop is missing', function (): void {
        $style = ['padding_y' => ['tablet' => '60px', 'mobile' => '40px']];

        expect($this->resolver->resolve($style, 'desktop')['padding_y'])->toBe('60px');
    });

    it('empty string entries are treated as absent and skipped', function (): void {
        $style = ['padding_y' => ['desktop' => '', 'tablet' => '60px']];

        expect($this->resolver->resolve($style, 'desktop')['padding_y'])->toBe('60px');
    });
});

describe('resolve() — validation', function (): void {
    it('throws on an unknown breakpoint name', function (): void {
        $this->resolver->resolve(['bg_color' => '#000'], 'watch');
    })->throws(InvalidArgumentException::class, "Unknown breakpoint 'watch'");

    it('rejects tablet_max <= mobile_max at construction', function (): void {
        new BreakpointStyleResolver(tabletMaxPx: 600, mobileMaxPx: 767);
    })->throws(InvalidArgumentException::class);
});

describe('toCss() — shape and structure', function (): void {
    it('returns an empty string when no resolvable declarations exist', function (): void {
        expect($this->resolver->toCss([], '.s-1'))->toBe('');
        expect($this->resolver->toCss(['padding_y' => null, 'alignment' => ''], '.s-1'))->toBe('');
    });

    it('emits a single desktop block for flat scalar values', function (): void {
        $css = $this->resolver->toCss(
            ['bg_color' => '#ff0000', 'alignment' => 'center'],
            '.s-1'
        );

        expect($css)->toContain('.s-1 {')
            ->and($css)->toContain('background-color: #ff0000 !important')
            ->and($css)->toContain('text-align: center !important')
            ->and($css)->not->toContain('@media');
    });

    it('emits a tablet media block only for properties that differ from desktop', function (): void {
        $css = $this->resolver->toCss(
            [
                'bg_color' => '#ff0000', // same at every breakpoint
                'padding_y' => ['desktop' => '80px', 'tablet' => '60px', 'mobile' => '40px'],
            ],
            '.s-1'
        );

        expect($css)->toContain('@media (max-width: 1023px)')
            ->and($css)->toContain('@media (max-width: 767px)')
            ->and($css)->toMatch('/@media \(max-width: 1023px\) \{ \.s-1 \{ padding-top: 60px !important; padding-bottom: 60px !important \} \}/')
            ->and($css)->toMatch('/@media \(max-width: 767px\) \{ \.s-1 \{ padding-top: 40px !important; padding-bottom: 40px !important \} \}/');
    });

    it('expands compound keys (padding_y) into top + bottom declarations', function (): void {
        $css = $this->resolver->toCss(['padding_y' => '80px'], '.s-1');

        expect($css)->toContain('padding-top: 80px !important')
            ->and($css)->toContain('padding-bottom: 80px !important');
    });

    it('converts snake_case keys to kebab-case CSS properties', function (): void {
        $css = $this->resolver->toCss(['padding_top' => '12px'], '.s-1');

        expect($css)->toContain('padding-top: 12px !important');
    });

    it('honours custom breakpoint thresholds passed at construction', function (): void {
        $resolver = new BreakpointStyleResolver(tabletMaxPx: 900, mobileMaxPx: 500);
        $css = $resolver->toCss(
            ['padding_y' => ['desktop' => '80px', 'tablet' => '60px']],
            '.s-1'
        );

        expect($css)->toContain('@media (max-width: 900px)')
            ->and($css)->not->toContain('@media (max-width: 1023px)');
    });

    it('omits the tablet @media block entirely when tablet equals desktop after inheritance', function (): void {
        // Only desktop defined → tablet inherits desktop → no diff → no @media.
        $css = $this->resolver->toCss(
            ['padding_y' => ['desktop' => '80px']],
            '.s-1'
        );

        expect($css)->toContain('padding-top: 80px')
            ->and($css)->not->toContain('@media');
    });
});

describe('thresholds()', function (): void {
    it('exposes the configured breakpoint thresholds for client bootstrap', function (): void {
        $resolver = new BreakpointStyleResolver(tabletMaxPx: 1200, mobileMaxPx: 600);

        expect($resolver->thresholds())->toBe([
            'tablet_max' => 1200,
            'mobile_max' => 600,
        ]);
    });
});
