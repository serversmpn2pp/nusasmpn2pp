(() => {
    'use strict';
    const root = document.querySelector('[data-grafik-kedatangan]');
    if (!root) return;
    const source = JSON.parse(root.querySelector('[data-chart-source]').textContent);
    const canvas = root.querySelector('[data-chart-canvas]');
    const summary = root.querySelector('[data-chart-summary]');
    const empty = root.querySelector('[data-chart-empty]');
    const table = root.querySelector('[data-chart-table]');
    let range = 'minggu';
    let type = 'bar';
    let chart;
    const format = new Intl.NumberFormat('id-ID');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function render() {
        const rows = source[range];
        const arrivals = rows.filter(row => row.menit !== null);
        const late = arrivals.filter(row => row.menit > 0);
        summary.textContent = `${format.format(arrivals.length)} kedatangan tercatat, ${format.format(late.length)} kali terlambat (${format.format(late.reduce((sum, row) => sum + row.menit, 0))} menit).`;
        table.replaceChildren(...rows.map(row => {
            const item = document.createElement('li');
            item.textContent = `${row.lengkap}: ${row.jam ? `${row.jam}, ${row.menit > 0 ? `${row.menit} menit terlambat` : 'tepat waktu'}` : row.status}`;
            return item;
        }));
        root.querySelectorAll('[data-chart-range]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.chartRange === range)));
        root.querySelectorAll('[data-chart-type]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.chartType === type)));
        empty.hidden = arrivals.length > 0;
        chart?.destroy();
        if (!window.Chart) {
            empty.hidden = false;
            empty.textContent = 'Grafik belum dapat dimuat. Rincian tersedia pada Data kedatangan.';
            return;
        }
        chart = new window.Chart(canvas, {
            type,
            data: {
                labels: rows.map(row => row.tanggal),
                datasets: [{
                    label: 'Keterlambatan (menit)',
                    data: rows.map(row => row.menit),
                    backgroundColor: rows.map(row => row.menit > 0 ? '#d4a90b' : '#0f766e'),
                    borderColor: type === 'line' ? '#15477a' : 'transparent',
                    borderWidth: type === 'line' ? 2 : 0,
                    borderRadius: 3,
                    maxBarThickness: 28,
                    minBarLength: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: rows.map(row => row.menit > 0 ? '#d4a90b' : '#0f766e'),
                    spanGaps: false,
                    tension: 0.2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reducedMotion ? false : { duration: 250 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: items => rows[items[0].dataIndex].lengkap,
                            label: item => `${rows[item.dataIndex].jam} - ${item.raw > 0 ? `${item.raw} menit terlambat` : 'Tepat waktu'}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#526b7d', maxRotation: 0, maxTicksLimit: 8, font: { family: 'Segoe UI, sans-serif', size: 11 } }, border: { display: false } },
                    y: { beginAtZero: true, suggestedMax: 10, ticks: { precision: 0, color: '#526b7d', font: { family: 'Segoe UI, sans-serif', size: 11 } }, grid: { color: '#edf1f4' }, border: { display: false }, title: { display: true, text: 'Menit', color: '#526b7d' } },
                },
            },
        });
    }
    root.querySelectorAll('[data-chart-range]').forEach(button => button.addEventListener('click', () => {
        range = button.dataset.chartRange;
        render();
    }));
    root.querySelectorAll('[data-chart-type]').forEach(button => button.addEventListener('click', () => {
        type = button.dataset.chartType;
        render();
    }));
    render();
})();
