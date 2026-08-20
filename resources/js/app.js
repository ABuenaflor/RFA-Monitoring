//

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initializeRfaDistributionChart();
});

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