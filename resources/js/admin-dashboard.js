/**
 * Admin Dashboard — ApexCharts initialization
 * Reads chart data from embedded JSON script tag, renders orders/revenue mixed chart.
 */

const AdminDashboard = {
    _chart: null,
    _apexLoader: null,

    async init() {
        await this.renderOrdersChart();
    },

    destroy() {
        if (this._chart) {
            this._chart.destroy();
            this._chart = null;
        }
    },

    async renderOrdersChart() {
        const el = document.getElementById('ordersChart');
        const dataEl = document.getElementById('orders-chart-data');
        if (!el || !dataEl) return;

        const ApexChartsLib = await this.resolveApexCharts();
        if (!ApexChartsLib) return;

        // Destroy previous instance if exists (PJAX re-navigation)
        this.destroy();

        let data;
        try {
            data = JSON.parse(dataEl.textContent);
        } catch (e) {
            console.warn('[AdminDashboard] Failed to parse chart data', e);
            return;
        }

        const options = {
            chart: {
                type: 'area',
                height: 320,
                fontFamily: 'Roboto, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            series: [
                {
                    name: 'Orders',
                    type: 'column',
                    data: data.orders || [],
                },
                {
                    name: 'Revenue (GEL)',
                    type: 'area',
                    data: data.revenue || [],
                },
            ],
            xaxis: {
                categories: data.labels || [],
                labels: {
                    rotate: -45,
                    rotateAlways: false,
                    style: { fontSize: '11px', colors: '#6c757d' },
                },
                tickAmount: 10,
            },
            yaxis: [
                {
                    title: { text: 'Orders', style: { color: '#6366f1', fontWeight: 500 } },
                    labels: { style: { colors: '#6366f1' } },
                    min: 0,
                    forceNiceScale: true,
                },
                {
                    opposite: true,
                    title: { text: 'Revenue (GEL)', style: { color: '#10b981', fontWeight: 500 } },
                    labels: {
                        style: { colors: '#10b981' },
                        formatter: (v) => v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v.toFixed(0),
                    },
                    min: 0,
                    forceNiceScale: true,
                },
            ],
            colors: ['#6366f1', '#10b981'],
            fill: {
                type: ['solid', 'gradient'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.1,
                    stops: [0, 90, 100],
                },
            },
            stroke: {
                width: [0, 2],
                curve: 'smooth',
            },
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    borderRadius: 3,
                },
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: '#f0f0f0',
                strokeDashArray: 3,
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val, { seriesIndex }) {
                        if (seriesIndex === 1) return 'GEL ' + val.toFixed(2);
                        return val + ' order' + (val !== 1 ? 's' : '');
                    },
                },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
            },
        };

        this._chart = new ApexChartsLib(el, options);
        this._chart.render();
    },

    async resolveApexCharts() {
        if (typeof window.ApexCharts === 'function') {
            return window.ApexCharts;
        }

        if (!this._apexLoader) {
            this._apexLoader = new Promise((resolve) => {
                const existing = document.querySelector('script[data-admin-apexcharts="1"]');
                if (existing) {
                    existing.addEventListener('load', () => resolve(window.ApexCharts || null), { once: true });
                    existing.addEventListener('error', () => resolve(null), { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = '/assets/vendors/apexcharts/apexcharts.min.js';
                script.async = true;
                script.dataset.adminApexcharts = '1';
                script.onload = () => resolve(window.ApexCharts || null);
                script.onerror = () => resolve(null);
                document.head.appendChild(script);
            });
        }

        return this._apexLoader;
    },
};

window.AdminDashboard = AdminDashboard;

export default AdminDashboard;
