/*
 | Lightweight Chart.js wrapper, loaded on demand so storefront pages stay small.
*/
export function registerCharts(Alpine) {
    Alpine.data('chart', (config) => ({
        instance: null,
        async init() {
            const { Chart, registerables } = await import('chart.js');
            Chart.register(...registerables);
            // Colours come from the CSS theme tokens in resources/css/app.css.
            const token = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
            const resolve = (value) => (typeof value === 'string' && value.startsWith('--') ? token(value, '#f5b027') : value);
            const ink = token('--color-ink', '#171923');
            const brand = token('--color-brand-600', '#f5b027');
            const brandLine = token('--color-brand-700', '#a16207');
            const success = token('--color-success-600', '#16a34a');
            const warning = token('--color-warning-600', '#d97706');
            const info = token('--color-info-600', '#2563eb');
            const danger = token('--color-danger-600', '#dc2626');
            const line = token('--color-line', '#e5e7eb');
            const muted = token('--color-muted', '#64748b');
            const palette = [brand, success, warning, info, ink, danger];
            const datasets = config.datasets.map((dataset, index) => ({
                borderColor: index === 0 && config.type === 'line' ? brandLine : palette[index % palette.length],
                backgroundColor: config.type === 'line' ? palette[index % palette.length] + '1f' : (config.type === 'bar' && config.datasets.length > 1 ? palette[index % palette.length] : palette),
                borderWidth: config.type === 'line' ? 2 : 0,
                fill: config.type === 'line' && index === 0,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderRadius: config.type === 'bar' ? 6 : 0,
                ...dataset,
                ...(dataset.backgroundColor ? { backgroundColor: Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor.map(resolve) : resolve(dataset.backgroundColor) } : {}),
                ...(dataset.borderColor ? { borderColor: resolve(dataset.borderColor) } : {}),
            }));
            this.instance = new Chart(this.$refs.canvas, {
                type: config.type ?? 'line',
                data: { labels: config.labels, datasets },
                options: {
                    indexAxis: config.indexAxis ?? 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: datasets.length > 1 || config.type === 'doughnut', labels: { color: muted, boxWidth: 10, usePointStyle: true } },
                        tooltip: {
                            callbacks: config.currency ? { label: (ctx) => ` ${ctx.dataset.label ?? ''} ${config.currency} ${Number(config.indexAxis === 'y' ? ctx.parsed.x : (ctx.parsed.y ?? ctx.parsed)).toLocaleString()}` } : {},
                        },
                    },
                    scales: config.type === 'doughnut' ? {} : {
                        x: config.indexAxis === 'y'
                            ? { grid: { color: line }, border: { display: false }, ticks: { color: muted, precision: config.currency ? undefined : 0, callback: (v) => Number(v).toLocaleString() }, beginAtZero: true }
                            : { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 8 } },
                        y: config.indexAxis === 'y'
                            ? { grid: { display: false }, ticks: { color: muted } }
                            : { grid: { color: line }, border: { display: false }, ticks: { color: muted, precision: config.currency ? undefined : 0, callback: (v) => Number(v).toLocaleString() }, beginAtZero: true },
                    },
                },
            });
        },
        destroy() {
            this.instance?.destroy();
        },
    }));
}
