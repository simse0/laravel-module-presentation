const AnimService = {
    counter(el, rawTarget, fmtFn, duration = 1400) {
        if (!el) {
            return;
        }

        const target = Number(rawTarget || 0);
        const format = typeof fmtFn === 'function' ? fmtFn : (v) => String(Math.round(v));
        const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

        el.textContent = format(0);
        const startedAt = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startedAt) / duration, 1);
            el.textContent = format(target * easeOutCubic(progress));

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    },

    bar(el, targetPct, delayMs = 0) {
        if (!el) {
            return;
        }

        const clamped = Math.max(0, Math.min(100, Number(targetPct || 0)));
        el.style.transition = 'none';
        el.style.width = '0%';

        setTimeout(() => {
            el.style.transition = 'width 700ms cubic-bezier(0.22, 1, 0.36, 1)';
            el.style.width = `${clamped}%`;
        }, delayMs);
    },

    drawLine(polylineEl, svgWidth, duration = 1200) {
        if (!polylineEl || !polylineEl.ownerSVGElement) {
            return;
        }

        const svg = polylineEl.ownerSVGElement;
        const defs = svg.querySelector('defs')
            || svg.insertBefore(document.createElementNS('http://www.w3.org/2000/svg', 'defs'), svg.firstChild);

        svg.querySelectorAll('[data-anim-star]').forEach((el) => el.remove());
        svg.querySelectorAll('[data-anim-clip]').forEach((el) => el.remove());
        polylineEl.removeAttribute('clip-path');

        const clipId = `clip-${Math.random().toString(36).slice(2)}`;
        const clipEl = document.createElementNS('http://www.w3.org/2000/svg', 'clipPath');
        clipEl.setAttribute('id', clipId);
        clipEl.setAttribute('data-anim-clip', '1');

        const clipRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        clipRect.setAttribute('x', '0');
        clipRect.setAttribute('y', '-20');
        clipRect.setAttribute('width', '0');
        clipRect.setAttribute('height', '9999');
        clipEl.appendChild(clipRect);
        defs.appendChild(clipEl);

        polylineEl.setAttribute('clip-path', `url(#${clipId})`);

        if (!svg.querySelector('#td-glow')) {
            const filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
            filter.setAttribute('id', 'td-glow');
            filter.setAttribute('x', '-100%');
            filter.setAttribute('y', '-100%');
            filter.setAttribute('width', '300%');
            filter.setAttribute('height', '300%');
            filter.innerHTML = '<feGaussianBlur stdDeviation="5" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>';
            defs.appendChild(filter);
        }

        const star = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        star.setAttribute('r', '8');
        star.setAttribute('fill', 'var(--highlight)');
        star.setAttribute('filter', 'url(#td-glow)');
        star.setAttribute('opacity', '1');
        star.setAttribute('data-anim-star', '1');
        svg.appendChild(star);

        const pointsRaw = (polylineEl.getAttribute('points') || '').trim();
        const points = pointsRaw
            .split(/[\s,]+/)
            .reduce((acc, value, index, arr) => {
                if (index % 2 === 0) {
                    acc.push({
                        x: parseFloat(value),
                        y: parseFloat(arr[index + 1] || '0'),
                    });
                }
                return acc;
            }, []);

        const startTime = performance.now();
        const ease = (t) => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);
        const width = Number(svgWidth || 1000);

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = ease(progress);
            clipRect.setAttribute('width', String(eased * width));

            if (points.length >= 2) {
                const segmentProgress = eased * (points.length - 1);
                const index = Math.min(Math.floor(segmentProgress), points.length - 2);
                const ratio = segmentProgress - index;
                const cx = points[index].x + (points[index + 1].x - points[index].x) * ratio;
                const cy = points[index].y + (points[index + 1].y - points[index].y) * ratio;
                star.setAttribute('cx', String(cx));
                star.setAttribute('cy', String(cy));
            }

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                star.setAttribute('opacity', '0');
            }
        };

        requestAnimationFrame(tick);
    },

    revealChart(el, duration = 400, delayMs = 0) {
        if (!el) {
            return;
        }

        el.style.transition = 'none';
        el.style.opacity = '0';
        el.style.transform = 'translateY(12px)';

        setTimeout(() => {
            el.style.transition = `opacity ${duration}ms ease, transform ${duration}ms ease`;
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, delayMs + 20);
    },
};

if (typeof window !== 'undefined') {
    window.AnimService = AnimService;
}
