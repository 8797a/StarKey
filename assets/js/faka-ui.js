function fakaInitScrollAnimation() {
    var items = document.querySelectorAll('.animate-on-scroll');
    if (!items.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (item) {
            item.classList.add('is-visible');
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    items.forEach(function (item) {
        observer.observe(item);
    });
}

function fakaInitAmbientCursor() {
    if (window.matchMedia('(hover: none), (pointer: coarse), (prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');
    canvas.className = 'ambient-cursor ambient-cursor-dust';
    document.body.appendChild(canvas);

    var width = window.innerWidth;
    var height = window.innerHeight;
    var ratio = 1;
    var cursor = { x: width / 2, y: height / 2 };
    var lastPos = { x: width / 2, y: height / 2 };
    var particles = [];
    var raf = null;
    var uiSelector = 'a, button, input, select, textarea, label, .glass-panel, .product-card, .pay-method, .checkout-panel, .header-stats, .site-title';
    var colors = ['#7c3aed', '#06b6d4', '#ec4899', '#f59e0b', '#22c55e', '#3b82f6'];

    function resizeCanvas() {
        ratio = Math.min(window.devicePixelRatio || 1, 1.25);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function isUiTarget(target) {
        return target && target.closest && target.closest(uiSelector);
    }

    function addParticle(x, y) {
        var angle = Math.random() * Math.PI * 2;
        var speed = 0.18 + Math.random() * 0.75;
        particles.push({
            x: x + (Math.random() - 0.5) * 10,
            y: y + (Math.random() - 0.5) * 10,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed - 0.25,
            spin: (Math.random() - 0.5) * 0.2,
            angle: Math.random() * Math.PI,
            size: 3.5 + Math.random() * 5.5,
            life: 1,
            decay: 0.052 + Math.random() * 0.032,
            color: colors[Math.floor(Math.random() * colors.length)]
        });

        if (particles.length > 68) {
            particles.splice(0, particles.length - 68);
        }
    }

    function drawSpark(p) {
        var alpha = Math.max(0, p.life);
        var size = p.size * (0.45 + p.life * 0.85);

        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.angle);
        ctx.globalAlpha = alpha;
        ctx.fillStyle = p.color;
        ctx.shadowColor = p.color;
        ctx.shadowBlur = 12 * alpha;

        ctx.beginPath();
        ctx.moveTo(0, -size);
        ctx.lineTo(size * 0.34, -size * 0.18);
        ctx.lineTo(size, 0);
        ctx.lineTo(size * 0.34, size * 0.18);
        ctx.lineTo(0, size);
        ctx.lineTo(-size * 0.34, size * 0.18);
        ctx.lineTo(-size, 0);
        ctx.lineTo(-size * 0.34, -size * 0.18);
        ctx.closePath();
        ctx.fill();

        if (p.life > 0.72) {
            ctx.globalAlpha = (p.life - 0.72) * 1.6;
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(0, 0, Math.max(1, size * 0.22), 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.restore();
    }

    function render() {
        ctx.clearRect(0, 0, width, height);
        ctx.globalCompositeOperation = 'lighter';

        for (var i = particles.length - 1; i >= 0; i--) {
            var p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vx *= 0.94;
            p.vy *= 0.94;
            p.angle += p.spin;
            p.life -= p.decay;

            if (p.life <= 0) {
                particles.splice(i, 1);
                continue;
            }

            drawSpark(p);
        }

        if (particles.length > 0) {
            raf = window.requestAnimationFrame(render);
        } else {
            raf = null;
            canvas.classList.remove('is-active');
        }
    }

    document.addEventListener('pointermove', function (event) {
        if (isUiTarget(event.target)) {
            return;
        }

        cursor.x = event.clientX;
        cursor.y = event.clientY;
        canvas.classList.add('is-active');

        var distance = Math.hypot(cursor.x - lastPos.x, cursor.y - lastPos.y);
        if (distance > 1) {
            var count = Math.min(7, Math.max(1, Math.round(distance / 22)));
            for (var i = 1; i <= count; i++) {
                var t = i / count;
                addParticle(
                    lastPos.x + (cursor.x - lastPos.x) * t,
                    lastPos.y + (cursor.y - lastPos.y) * t
                );
            }
            lastPos.x = cursor.x;
            lastPos.y = cursor.y;
        }

        if (!raf) {
            raf = window.requestAnimationFrame(render);
        }
    }, { passive: true });

    window.addEventListener('resize', resizeCanvas, { passive: true });

    document.addEventListener('pointerleave', function () {
        particles.length = 0;
        canvas.classList.remove('is-active');
        ctx.clearRect(0, 0, width, height);
    });

    resizeCanvas();
}

document.addEventListener('DOMContentLoaded', fakaInitScrollAnimation);
document.addEventListener('DOMContentLoaded', fakaInitAmbientCursor);
