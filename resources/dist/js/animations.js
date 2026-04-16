/**
 * Visual Builder — entrance animation observer.
 *
 * Watches [data-vb-animation] elements and toggles .vb-anim-play when
 * they enter the viewport. Pairs with animations.css for the CSS keyframes.
 *
 * Design notes:
 *  - Single shared IntersectionObserver, 10% threshold, 50px bottom margin
 *    so animations trigger just before the element is fully in view.
 *  - One-shot: observer unobserves after first play to avoid re-triggering
 *    on scroll-up. If you need re-triggering, remove .vb-anim-play and
 *    re-observe.
 *  - Fallback: browsers without IntersectionObserver (pre-2017) get immediate
 *    play — the animation still runs, just without the scroll trigger.
 *  - Idempotent boot: safe to include on pages that don't use animations
 *    (no-ops when no elements match).
 */
(function () {
    'use strict';

    if (typeof IntersectionObserver === 'undefined') {
        document.querySelectorAll('[data-vb-animation]').forEach(function (el) {
            el.classList.add('vb-anim-play');
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('vb-anim-play');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px',
    });

    function observeAll() {
        document.querySelectorAll('[data-vb-animation]:not(.vb-anim-play)').forEach(function (el) {
            observer.observe(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeAll);
    } else {
        observeAll();
    }

    // Re-scan when asked (e.g. after AJAX section replacement)
    window.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'vb-rescan-animations') observeAll();
    });
})();
