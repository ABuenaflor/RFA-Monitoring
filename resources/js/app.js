//

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initializeRfaDistributionChart();
    initializeFilingTrendChart();
});

/**
 * RFAs filed per day, one smooth line per mode of filing.
 */
function initializeFilingTrendChart() {
    const chartElement = document.getElementById('rfaFilingTrendChart');

    if (!chartElement) {
        return;
    }

    const trend = JSON.parse(chartElement.dataset.trend ?? '{}');

    const series = (label, values, color, fill) => ({
        label,
        data: values ?? [],
        borderColor: color,
        backgroundColor: fill ?? color,
        fill: Boolean(fill),
        tension: 0.4,

        // Smooth, but never curves above or below the real counts.
        cubicInterpolationMode: 'monotone',
        borderWidth: 3,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBorderWidth: 2,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: color,
    });

    new Chart(chartElement, {
        type: 'line',

        data: {
            labels: trend.labels ?? [],

            datasets: [
                series('On-site', trend.onsite, '#2563eb', 'rgba(37, 99, 235, 0.08)'),
                series('Online', trend.online, '#ea580c'),
            ],
        },

        options: {
            responsive: true,

            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            scales: {
                x: {
                    grid: {
                        display: false,
                    },

                    ticks: {
                        color: '#64748b',
                    },
                },

                y: {
                    beginAtZero: true,

                    suggestedMax: 5,

                    grid: {
                        color: '#f1f5f9',
                    },

                    border: {
                        display: false,
                    },

                    ticks: {
                        color: '#64748b',
                        precision: 0,
                    },
                },
            },

            plugins: {
                legend: {
                    position: 'bottom',

                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        color: '#475569',

                        // Hollow legend markers, matching the points.
                        generateLabels(chart) {
                            return Chart.defaults.plugins.legend.labels
                                .generateLabels(chart)
                                .map((item) => ({
                                    ...item,
                                    fillStyle: '#ffffff',
                                    lineWidth: 2,
                                }));
                        },
                    },
                },

                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw);

                            return `${context.dataset.label}: ${value} RFA${value === 1 ? '' : 's'} filed`;
                        },
                    },
                },
            },
        },
    });
}

function initializeRfaDistributionChart() {
    const chartElement = document.getElementById('rfaDistributionChart');

    if (!chartElement) {
        return;
    }

    const pending = Number(chartElement.dataset.pending ?? 0);
    const ongoing = Number(chartElement.dataset.ongoing ?? 0);
    const disposed = Number(chartElement.dataset.disposed ?? 0);

    const total = pending + ongoing + disposed;

    if (total === 0) {
        return;
    }

    new Chart(chartElement, {
        type: 'doughnut',

        data: {
            labels: [
                'Pending RFAs',
                'Ongoing RFAs',
                'Disposed RFAs',
            ],

            datasets: [
                {
                    data: [
                        pending,
                        ongoing,
                        disposed,
                    ],

                    backgroundColor: [
                        '#f59e0b',
                        '#2563eb',
                        '#16a34a',
                    ],

                    borderColor: [
                        '#ffffff',
                        '#ffffff',
                        '#ffffff',
                    ],

                    borderWidth: 4,
                    hoverOffset: 8,
                },
            ],
        },

        options: {
            responsive: true,

            maintainAspectRatio: false,

            cutout: '72%',

            plugins: {
                legend: {
                    display: false,
                },

                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw);
                            const percentage = ((value / total) * 100).toFixed(1);

                            return `${context.label}: ${value} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}