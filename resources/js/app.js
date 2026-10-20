//

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('notificationBell', notificationBell);

Alpine.data('seadoDraw', seadoDraw);

Alpine.start();

/**
 * SEADO Assignment random draw: picks one of the least-handled SEADOs with
 * equal odds and shows a draw record (reference, time, who drew it). The
 * winner is chosen with crypto randomness before the animation starts;
 * the highlight only steps down the list and settles on it.
 */
function seadoDraw(seados, { drawnBy = null, pool = '' } = {}) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Delay before each highlight step: quick at first, slowing to a stop.
    const STEP_DELAYS = reduceMotion
        ? []
        : [60, 60, 60, 60, 70, 70, 80, 90, 100, 120, 140, 170, 210, 260, 320];

    const randomInt = (max) => {
        const value = new Uint32Array(1);

        crypto.getRandomValues(value);

        return value[0] % max;
    };

    const randomHex = (length) => {
        const bytes = new Uint8Array(Math.ceil(length / 2));

        crypto.getRandomValues(bytes);

        return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('').slice(0, length).toUpperCase();
    };

    const pad = (number) => String(number).padStart(2, '0');

    return {
        candidates: [...seados],
        phase: 'idle',
        highlight: null,
        record: null,
        copied: false,

        draw() {
            if (this.phase !== 'idle' || this.candidates.length === 0) {
                return;
            }

            const count = this.candidates.length;

            const winnerIndex = randomInt(count);

            this.phase = 'drawing';
            this.record = null;
            this.copied = false;

            // Walk the highlight down the list so it lands on the winner on the last step.
            const steps = STEP_DELAYS.length;

            let position = (((winnerIndex - steps) % count) + count) % count;

            const step = (index) => {
                if (index >= steps) {
                    this.finish(winnerIndex);

                    return;
                }

                position = (position + 1) % count;
                this.highlight = this.candidates[position].name;

                setTimeout(() => step(index + 1), STEP_DELAYS[index]);
            };

            step(0);
        },

        finish(winnerIndex) {
            const now = new Date();

            const seado = this.candidates[winnerIndex];

            this.highlight = seado.name;
            this.phase = 'idle';

            this.record = {
                seado,
                reference: `SD-${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${randomHex(4)}`,
                drawnAt: now.toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                }),
                drawnBy,
                pool,
                poolSize: this.candidates.length,
            };
        },

        copyRecord() {
            if (! this.record) {
                return;
            }

            const record = this.record;

            const text = [
                `SEADO draw ${record.reference}`,
                `Selected: ${record.seado.name}`,
                `Drawn: ${record.drawnAt}${record.drawnBy ? ` by ${record.drawnBy}` : ''}`,
                `Pool (${record.poolSize}, ${record.pool}): ${this.candidates.map((seado) => seado.name).join(', ')}`,
                'Method: secure random, equal odds',
            ].join('\n');

            navigator.clipboard?.writeText(text).then(() => {
                this.copied = true;

                setTimeout(() => (this.copied = false), 2000);
            });
        },
    };
}

/**
 * Header bell: polls the signed-in user's notifications and shows a
 * desktop notification for each new nearing / due today / beyond PCT
 * alert. Works while the system is open in a tab; the browser only allows
 * it on HTTPS or localhost.
 */
function notificationBell({ feedUrl, userId, unread, iconUrl }) {
    const POLL_MS = 60 * 1000;

    const MAX_POPUPS = 4;

    const storageKey = `rfa-desktop-notified:${userId}`;

    const readNotified = () => {
        try {
            return new Set(JSON.parse(localStorage.getItem(storageKey) ?? '[]'));
        } catch {
            return new Set();
        }
    };

    const saveNotified = (keys) => {
        try {
            // Keep the list short; old keys belong to cleared alerts.
            localStorage.setItem(storageKey, JSON.stringify([...keys].slice(-300)));
        } catch {
            // Storage blocked: worst case a popup repeats on the next visit.
        }
    };

    return {
        open: false,
        loaded: false,
        unread,
        pct: { nearing: 0, due: 0, beyond: 0 },
        items: [],
        desktop: 'Notification' in window && window.isSecureContext
            ? Notification.permission
            : 'unsupported',

        init() {
            this.refresh();

            setInterval(() => this.refresh(), POLL_MS);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }
            });
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.refresh();
            }
        },

        async refresh() {
            try {
                const response = await fetch(feedUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const feed = await response.json();

                this.unread = feed.unread;
                this.pct = feed.pct;
                this.items = feed.items;
                this.loaded = true;

                this.notifyDesktop(feed.items);
            } catch {
                // Offline or signed out: keep the last state.
            }
        },

        async enableDesktop() {
            if (this.desktop === 'unsupported') {
                return;
            }

            this.desktop = await Notification.requestPermission();

            if (this.desktop === 'granted') {
                this.notifyDesktop(this.items);
            }
        },

        notifyDesktop(items) {
            if (this.desktop !== 'granted') {
                return;
            }

            const notified = readNotified();

            // A key includes the severity, so an escalation pops up again.
            const fresh = items.filter(
                (item) => item.level && !item.read && !notified.has(item.key)
            );

            fresh.slice(0, MAX_POPUPS).forEach((item) => {
                const popup = new Notification(item.title, {
                    body: item.message ?? '',
                    tag: `rfa-${item.id}`,
                    icon: iconUrl,
                    requireInteraction: item.severity === 'critical',
                });

                popup.onclick = () => {
                    window.focus();
                    window.location.href = item.url;
                    popup.close();
                };
            });

            if (fresh.length > MAX_POPUPS) {
                new Notification('RFA Monitoring', {
                    body: `${fresh.length - MAX_POPUPS} more PCT alerts — open the bell to see them.`,
                    tag: 'rfa-more',
                    icon: iconUrl,
                });
            }

            fresh.forEach((item) => notified.add(item.key));

            saveNotified(notified);
        },
    };
}

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