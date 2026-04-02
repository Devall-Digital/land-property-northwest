/**
 * LPNW Theme - Frontend JavaScript
 */
(function () {
    'use strict';

    var GOLD = '240, 165, 0';
    var TEAL = '0, 212, 170';

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
     * Fine dust on #lpnw-hero-particles (if present).
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
        var n = 56;
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
                    r: 0.35 + Math.random() * 1.8,
                    vx: (Math.random() - 0.5) * 0.4,
                    vy: -0.12 - Math.random() * 0.5,
                    a: 0.1 + Math.random() * 0.38
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

    /**
     * Cinematic full-hero canvas: aurora, stars, subtle lightning, heat shimmer.
     */
    function initHeroFx() {
        var canvas = document.getElementById('lpnw-hero-fx');
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
        var w = 1;
        var h = 1;
        var stars = [];
        var ns = 140;
        var si;
        var bolt = { active: 0, x: 0, w: 0, phase: 0 };
        var t0 = performance.now();
        var rafId = 0;
        var running = false;
        var mx = 0.5;
        var my = 0.35;

        function placeStars() {
            stars.length = 0;
            for (si = 0; si < ns; si++) {
                stars.push({
                    x: Math.random(),
                    y: Math.random() * 0.72,
                    r: Math.random() * 1.2 + 0.2,
                    tw: 1.5 + Math.random() * 4,
                    ph: Math.random() * Math.PI * 2,
                    a: 0.15 + Math.random() * 0.55
                });
            }
        }

        function resize() {
            var rect = hero.getBoundingClientRect();
            w = Math.max(1, Math.floor(rect.width));
            h = Math.max(1, Math.floor(rect.height));
            canvas.width = Math.floor(w * dpr);
            canvas.height = Math.floor(h * dpr);
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            placeStars();
        }

        function drawAurora(t) {
            var g = ctx.createLinearGradient(0, 0, w, h * 0.55);
            var wave = Math.sin(t * 0.00035) * 0.08;
            g.addColorStop(0, 'rgba(' + TEAL + ', ' + (0.04 + wave) + ')');
            g.addColorStop(0.35, 'rgba(' + GOLD + ', ' + (0.05 + wave * 0.5) + ')');
            g.addColorStop(0.55, 'rgba(96, 165, 250, ' + (0.04 + wave) + ')');
            g.addColorStop(1, 'rgba(15, 29, 53, 0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, w, h * 0.62);

            var g2 = ctx.createRadialGradient(
                w * (0.25 + mx * 0.15),
                h * (0.12 + my * 0.08),
                0,
                w * 0.35,
                h * 0.2,
                w * 0.85
            );
            g2.addColorStop(0, 'rgba(255,255,255,0.06)');
            g2.addColorStop(0.4, 'rgba(' + TEAL + ',0.04)');
            g2.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, w, h * 0.5);
        }

        function drawStars(t) {
            var j;
            var s;
            var pulse;
            for (j = 0; j < stars.length; j++) {
                s = stars[j];
                pulse = 0.55 + 0.45 * Math.sin(t * 0.0012 / s.tw + s.ph);
                ctx.beginPath();
                ctx.arc(s.x * w, s.y * h, s.r * pulse, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(255,255,255,' + (s.a * pulse * 0.85) + ')';
                ctx.fill();
            }
        }

        function maybeBolt(t) {
            if (bolt.active > 0) {
                bolt.active -= 1;
                var alpha = bolt.active / 8;
                ctx.strokeStyle = 'rgba(200, 230, 255,' + (alpha * 0.35) + ')';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.moveTo(bolt.x, 0);
                ctx.lineTo(bolt.x + bolt.w * 0.3, h * 0.22);
                ctx.lineTo(bolt.x - bolt.w * 0.2, h * 0.38);
                ctx.stroke();
                return;
            }
            if (Math.random() < 0.0009) {
                bolt.active = 8;
                bolt.x = Math.random() * w * 0.7 + w * 0.1;
                bolt.w = 40 + Math.random() * 80;
            }
        }

        function drawHeatShimmer(t) {
            var y = h * 0.42 + Math.sin(t * 0.0008) * 4;
            var grad = ctx.createLinearGradient(0, y, 0, h);
            grad.addColorStop(0, 'rgba(' + GOLD + ',0)');
            grad.addColorStop(0.5, 'rgba(' + GOLD + ',0.04)');
            grad.addColorStop(1, 'rgba(' + TEAL + ',0.03)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, y, w, h - y);
        }

        function frame(now) {
            if (!running) {
                return;
            }
            var t = now - t0;
            ctx.clearRect(0, 0, w, h);
            drawAurora(t);
            drawStars(t);
            drawHeatShimmer(t);
            maybeBolt(t);
            rafId = window.requestAnimationFrame(frame);
        }

        function startLoop() {
            if (running) {
                return;
            }
            running = true;
            t0 = performance.now();
            rafId = window.requestAnimationFrame(frame);
        }

        function stopLoop() {
            running = false;
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = 0;
            }
        }

        hero.addEventListener('mousemove', function (e) {
            var r = hero.getBoundingClientRect();
            mx = (e.clientX - r.left) / r.width;
            my = (e.clientY - r.top) / r.height;
        }, { passive: true });

        resize();
        window.addEventListener('resize', resize, { passive: true });

        if ('IntersectionObserver' in window) {
            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        startLoop();
                    } else {
                        stopLoop();
                    }
                });
            }, { rootMargin: '100px' });
            obs.observe(hero);
        } else {
            startLoop();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSmoothScroll();
        initAnimateOnScroll();
        initHeroFx();
        initHeroParticles();
    });
})();
