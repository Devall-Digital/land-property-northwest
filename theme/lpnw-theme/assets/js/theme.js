/**
 * LPNW Theme - Frontend JavaScript (lightweight: no full-screen canvas loops).
 */
(function () {
    'use strict';

    function initSmoothScroll() {
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: reduceMotion ? 'auto' : 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    function initAnimateOnScroll() {
        var elements = document.querySelectorAll('.lpnw-animate');
        if (!elements.length) return;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            elements.forEach(function (el) { el.classList.add('lpnw-visible'); });
            return;
        }

        if (!('IntersectionObserver' in window)) {
            elements.forEach(function (el) { el.classList.add('lpnw-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('lpnw-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        elements.forEach(function (el) { observer.observe(el); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSmoothScroll();
        initAnimateOnScroll();
    });
})();
