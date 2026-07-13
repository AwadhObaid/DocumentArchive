
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function cssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    const palette = [
        '#2563eb', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#14b8a6', '#f97316', '#64748b', '#0ea5e9', '#84cc16', '#ec4899'
    ];

    function normalizeItems(items, limit) {
        if (!Array.isArray(items)) return [];
        return items
            .map(function (item) {
                return {
                    label: String(item.label || item.day || 'غير محدد'),
                    total: Number(item.total || item.value || 0)
                };
            })
            .filter(function (item) { return item.total >= 0 && item.label !== ''; })
            .slice(0, limit || items.length);
    }

    function setupCanvas(canvas) {
        const parent = canvas.parentElement;
        const rect = parent.getBoundingClientRect();
        const width = Math.max(320, Math.floor(rect.width - 28));
        const height = Number(canvas.getAttribute('height') || 240);
        const ratio = window.devicePixelRatio || 1;

        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);
        ctx.direction = 'rtl';
        ctx.textAlign = 'right';
        ctx.font = '12px Tahoma, Arial, sans-serif';
        return { ctx: ctx, width: width, height: height };
    }

    function drawEmpty(canvas, message) {
        const wrap = document.createElement('div');
        wrap.className = 'smart-chart-empty';
        wrap.textContent = message || 'لا توجد بيانات كافية للرسم البياني.';
        canvas.replaceWith(wrap);
    }

    function drawAxes(ctx, x, y, w, h, max) {
        ctx.strokeStyle = 'rgba(148, 163, 184, .35)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x, y + h);
        ctx.lineTo(x + w, y + h);
        ctx.stroke();

        ctx.fillStyle = cssVar('--muted', '#64748b');
        ctx.textAlign = 'left';
        ctx.direction = 'ltr';
        ctx.fillText(String(max), x + 4, y + 12);
        ctx.fillText('0', x + 4, y + h - 4);
        ctx.direction = 'rtl';
        ctx.textAlign = 'right';
    }

    function truncate(text, max) {
        text = String(text || '');
        return text.length > max ? text.slice(0, max - 1) + '…' : text;
    }

    function drawBar(canvas, items) {
        items = normalizeItems(items, 8);
        if (!items.length) return drawEmpty(canvas);

        const s = setupCanvas(canvas);
        const ctx = s.ctx;
        const width = s.width;
        const height = s.height;
        const margin = { top: 18, right: 20, bottom: 38, left: 36 };
        const chartW = width - margin.right - margin.left;
        const chartH = height - margin.top - margin.bottom;
        const max = Math.max(1, ...items.map(function (i) { return i.total; }));
        const barGap = 10;
        const barW = Math.max(18, (chartW / items.length) - barGap);

        drawAxes(ctx, margin.left, margin.top, chartW, chartH, max);
        items.forEach(function (item, index) {
            const x = margin.left + index * (barW + barGap) + 8;
            const h = Math.round((item.total / max) * (chartH - 10));
            const y = margin.top + chartH - h;
            ctx.fillStyle = palette[index % palette.length];
            roundRect(ctx, x, y, barW, h, 8, true, false);

            ctx.fillStyle = cssVar('--text', '#0f172a');
            ctx.textAlign = 'center';
            ctx.direction = 'rtl';
            ctx.fillText(String(item.total), x + barW / 2, y - 5);

            ctx.save();
            ctx.translate(x + barW / 2, margin.top + chartH + 18);
            ctx.rotate(-Math.PI / 8);
            ctx.fillStyle = cssVar('--muted', '#64748b');
            ctx.fillText(truncate(item.label, 13), 0, 0);
            ctx.restore();
        });
    }

    function drawHorizontalBar(canvas, items) {
        items = normalizeItems(items, 10);
        if (!items.length) return drawEmpty(canvas);

        const s = setupCanvas(canvas);
        const ctx = s.ctx;
        const width = s.width;
        const height = s.height;
        const margin = { top: 16, right: 122, bottom: 18, left: 42 };
        const chartW = width - margin.right - margin.left;
        const chartH = height - margin.top - margin.bottom;
        const max = Math.max(1, ...items.map(function (i) { return i.total; }));
        const rowH = Math.max(18, chartH / items.length);

        items.forEach(function (item, index) {
            const y = margin.top + index * rowH + 4;
            const h = Math.max(12, rowH - 8);
            const w = Math.round((item.total / max) * chartW);

            ctx.fillStyle = 'rgba(148, 163, 184, .14)';
            roundRect(ctx, margin.left, y, chartW, h, 8, true, false);

            ctx.fillStyle = palette[index % palette.length];
            roundRect(ctx, margin.left + chartW - w, y, w, h, 8, true, false);

            ctx.fillStyle = cssVar('--text', '#0f172a');
            ctx.textAlign = 'right';
            ctx.direction = 'rtl';
            ctx.fillText(truncate(item.label, 18), width - 14, y + h - 4);

            ctx.textAlign = 'left';
            ctx.direction = 'ltr';
            ctx.fillText(String(item.total), 8, y + h - 4);
        });
    }

    function drawLine(canvas, items) {
        items = normalizeItems(items, 120);
        if (!items.length) return drawEmpty(canvas);

        const s = setupCanvas(canvas);
        const ctx = s.ctx;
        const width = s.width;
        const height = s.height;
        const margin = { top: 18, right: 20, bottom: 36, left: 42 };
        const chartW = width - margin.right - margin.left;
        const chartH = height - margin.top - margin.bottom;
        const max = Math.max(1, ...items.map(function (i) { return i.total; }));

        drawAxes(ctx, margin.left, margin.top, chartW, chartH, max);

        const step = items.length > 1 ? chartW / (items.length - 1) : chartW;
        const points = items.map(function (item, index) {
            return {
                x: margin.left + index * step,
                y: margin.top + chartH - ((item.total / max) * (chartH - 8)),
                item: item
            };
        });

        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 3;
        ctx.beginPath();
        points.forEach(function (point, index) {
            if (index === 0) ctx.moveTo(point.x, point.y);
            else ctx.lineTo(point.x, point.y);
        });
        ctx.stroke();

        points.forEach(function (point, index) {
            ctx.fillStyle = palette[index % palette.length];
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
            ctx.fill();
        });

        ctx.fillStyle = cssVar('--muted', '#64748b');
        ctx.textAlign = 'center';
        const first = items[0];
        const last = items[items.length - 1];
        ctx.fillText(truncate(first.label, 12), margin.left, height - 8);
        ctx.fillText(truncate(last.label, 12), margin.left + chartW, height - 8);
    }

    function drawDonut(canvas, items) {
        items = normalizeItems(items, 8).filter(function (item) { return item.total > 0; });
        if (!items.length) return drawEmpty(canvas);

        const s = setupCanvas(canvas);
        const ctx = s.ctx;
        const width = s.width;
        const height = s.height;
        const total = items.reduce(function (sum, item) { return sum + item.total; }, 0);
        const cx = Math.floor(width * 0.62);
        const cy = Math.floor(height * 0.48);
        const radius = Math.max(45, Math.min(width, height) * 0.26);
        let angle = -Math.PI / 2;

        items.forEach(function (item, index) {
            const slice = (item.total / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, angle, angle + slice);
            ctx.closePath();
            ctx.fillStyle = palette[index % palette.length];
            ctx.fill();
            angle += slice;
        });

        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 0.58, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';

        ctx.fillStyle = cssVar('--text', '#0f172a');
        ctx.textAlign = 'center';
        ctx.font = 'bold 18px Tahoma, Arial, sans-serif';
        ctx.fillText(String(total), cx, cy + 6);

        ctx.font = '12px Tahoma, Arial, sans-serif';
        ctx.textAlign = 'right';
        items.forEach(function (item, index) {
            const y = 24 + index * 24;
            ctx.fillStyle = palette[index % palette.length];
            roundRect(ctx, 12, y - 10, 14, 14, 4, true, false);
            ctx.fillStyle = cssVar('--text', '#0f172a');
            ctx.fillText(truncate(item.label, 18) + ' - ' + item.total, width - 12, y + 2);
        });
    }

    function roundRect(ctx, x, y, w, h, r, fill, stroke) {
        if (w < 0) { x += w; w = Math.abs(w); }
        if (h < 0) { y += h; h = Math.abs(h); }
        r = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
        if (fill) ctx.fill();
        if (stroke) ctx.stroke();
    }

    function renderCharts() {
        const source = document.getElementById('smartReportChartData');
        if (!source) return;

        let payload = {};
        try {
            payload = JSON.parse(source.textContent || '{}');
        } catch (error) {
            payload = {};
        }

        document.querySelectorAll('[data-smart-chart]').forEach(function (canvas) {
            const key = canvas.getAttribute('data-smart-chart');
            const type = canvas.getAttribute('data-chart-type') || 'bar';
            const items = payload[key] || [];

            if (type === 'line') return drawLine(canvas, items);
            if (type === 'horizontal-bar') return drawHorizontalBar(canvas, items);
            if (type === 'donut') return drawDonut(canvas, items);
            return drawBar(canvas, items);
        });
    }

    function setupCopyButton() {
        const copyButton = document.querySelector('[data-copy-smart-report]');
        const output = document.getElementById('smartReportOutput');

        if (!copyButton || !output) return;

        copyButton.addEventListener('click', async function () {
            const text = output.innerText || output.textContent || '';
            try {
                await navigator.clipboard.writeText(text);
                const original = copyButton.textContent;
                copyButton.textContent = 'تم النسخ';
                setTimeout(function () { copyButton.textContent = original; }, 1600);
            } catch (error) {
                const range = document.createRange();
                range.selectNodeContents(output);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    }

    function setupPrintButton() {
        const button = document.querySelector('[data-smart-print]');
        if (!button) return;
        button.addEventListener('click', function () { window.print(); });
    }

    ready(function () {
        setupCopyButton();
        setupPrintButton();
        renderCharts();
    });

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        if (!document.getElementById('smartReportChartData')) return;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            document.querySelectorAll('.smart-chart-empty').forEach(function (item) { item.remove(); });
            renderCharts();
        }, 220);
    });
})();
