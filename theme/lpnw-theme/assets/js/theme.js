/**
 * LPNW Theme - Frontend JavaScript (lightweight: no full-screen canvas loops).
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: reducedMotion ? 'auto' : 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    function initAnimateOnScroll() {
        var elements = document.querySelectorAll('.lpnw-animate');
        if (!elements.length) return;

        if (reducedMotion) {
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

    function initStaggeredCardReveal() {
        if (reducedMotion) return;
        if (!('IntersectionObserver' in window)) return;

        var grids = document.querySelectorAll('.lpnw-property-list--grid, .lpnw-dashboard__action-cards');
        if (!grids.length) return;

        var cardObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var cards = entry.target.querySelectorAll('.lpnw-property-card, .lpnw-action-card');
                cards.forEach(function (card, i) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(24px)';
                    setTimeout(function () {
                        card.style.transition = 'opacity 0.45s cubic-bezier(0.22,1,0.36,1), transform 0.45s cubic-bezier(0.22,1,0.36,1)';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 80 * i);
                });
                cardObserver.unobserve(entry.target);
            });
        }, { threshold: 0.05 });

        grids.forEach(function (grid) { cardObserver.observe(grid); });
    }

    function initStatCounters() {
        if (reducedMotion) return;
        if (!('IntersectionObserver' in window)) return;

        var statNums = document.querySelectorAll('.lpnw-stat-card__number');
        if (!statNums.length) return;

        var countObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                countObserver.unobserve(el);

                var text = el.textContent.trim();
                var num = parseInt(text.replace(/[^0-9]/g, ''), 10);
                if (isNaN(num) || num < 1) return;

                var duration = Math.min(1200, Math.max(400, num * 3));
                var start = performance.now();
                var suffix = text.replace(/[0-9,.\s]/g, '');

                function step(now) {
                    var progress = Math.min(1, (now - start) / duration);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    var current = Math.round(eased * num);
                    el.textContent = current.toLocaleString() + suffix;
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
        }, { threshold: 0.3 });

        statNums.forEach(function (el) { countObserver.observe(el); });
    }

    function initButtonShine() {
        if (reducedMotion) return;

        document.querySelectorAll('.lpnw-btn--primary').forEach(function (btn) {
            btn.addEventListener('mousemove', function (e) {
                var rect = btn.getBoundingClientRect();
                btn.style.setProperty('--lpnw-shine-x', ((e.clientX - rect.left) / rect.width * 100).toFixed(1) + '%');
                btn.style.setProperty('--lpnw-shine-y', ((e.clientY - rect.top) / rect.height * 100).toFixed(1) + '%');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSmoothScroll();
        initAnimateOnScroll();
        initStaggeredCardReveal();
        initStatCounters();
        initButtonShine();
    });
})();
