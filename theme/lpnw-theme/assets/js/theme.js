/**
 * LPNW Theme - Frontend JavaScript
 */
(function () {
    'use strict';

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    function initAnimateOnScroll() {
        var elements = document.querySelectorAll('.lpnw-animate');
        if (!elements.length) return;

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
