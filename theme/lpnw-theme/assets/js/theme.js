/**
 * LPNW Theme - Frontend JavaScript
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

    /**
     * Subtle floating particles on the front-page hero canvas (if present).
     */
    function initHeroParticles() {
        var canvas = document.getElementById('lpnw-hero-particles');
        if (!canvas || !canvas.getContext) {
            return;
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        var hero = canvas.closest('.lpnw-hero');
        if (!hero) {
            return;
        }
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var particles = [];
        var n = 48;
        var i;
        var w;
        var h;
        var rafId = 0;
        var running = false;

        function resize() {
            var rect = hero.getBoundingClientRect();
            w = Math.max(1, Math.floor(rect.width));
            h = Math.max(1, Math.floor(rect.height));
            canvas.width = Math.floor(w * dpr);
            canvas.height = Math.floor(h * dpr);
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        function seed() {
            particles.length = 0;
            for (i = 0; i < n; i++) {
                particles.push({
                    x: Math.random() * w,
                    y: Math.random() * h,
                    r: 0.4 + Math.random() * 1.6,
                    vx: (Math.random() - 0.5) * 0.35,
                    vy: -0.15 - Math.random() * 0.55,
                    a: 0.12 + Math.random() * 0.35
                });
            }
        }

        function tick() {
            if (!running) {
                return;
            }
            var j;
            var p;
            ctx.clearRect(0, 0, w, h);
            for (j = 0; j < particles.length; j++) {
                p = particles[j];
                p.x += p.vx;
                p.y += p.vy;
                if (p.y < -4) {
                    p.y = h + 4;
                    p.x = Math.random() * w;
                }
                if (p.x < -4) {
                    p.x = w + 4;
                } else if (p.x > w + 4) {
                    p.x = -4;
                }
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(255,255,255,' + p.a + ')';
                ctx.fill();
            }
            rafId = window.requestAnimationFrame(tick);
        }

        function startLoop() {
            if (running) {
                return;
            }
            running = true;
            rafId = window.requestAnimationFrame(tick);
        }

        function stopLoop() {
            running = false;
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = 0;
            }
        }

        resize();
        seed();

        window.addEventListener('resize', function () {
            resize();
            seed();
        }, { passive: true });

        if ('IntersectionObserver' in window) {
            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        startLoop();
                    } else {
                        stopLoop();
                    }
                });
            }, { rootMargin: '80px' });
            obs.observe(hero);
        } else {
            startLoop();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSmoothScroll();
        initAnimateOnScroll();
        initHeroParticles();
    });
})();
