//

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('notificationBell', notificationBell);

Alpine.data('seadoRoulette', seadoRoulette);

Alpine.start();

/**
 * SEADO Assignment roulette: shuffles the least-handled SEADOs onto a
 * wheel, spins it, and shows the one that lands under the pointer. The
 * winner is chosen with crypto randomness before the spin starts; the
 * animation only lands on it.
 */
function seadoRoulette(seados) {
    const PALETTE = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#e11d48'];

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const SHUFFLE_TICKS = reduceMotion ? 1 : 10;

    const SHUFFLE_MS = 90;

    const SPIN_MS = reduceMotion ? 600 : 4800;

    const FULL_TURNS = reduceMotion ? 1 : 6;

    const randomInt = (max) => {
        const value = new Uint32Array(1);

        crypto.getRandomValues(value);

        return value[0] % max;
    };

    const shuffled = (list) => {
        const copy = [...list];

        for (let i = copy.length - 1; i > 0; i--) {
            const j = randomInt(i + 1);

            [copy[i], copy[j]] = [copy[j], copy[i]];
        }

        return copy;
    };

    return {
        segments: [...seados],
        rotation: 0,
        transition: 'none',
        phase: 'idle',
        winner: null,
        winnerIndex: null,
        highlight: null,
        showResult: false,
        fallbackTimer: null,

        slice() {
            return 360 / Math.max(this.segments.length, 1);
        },

        color(index) {
            return PALETTE[index % PALETTE.length];
        },

        gradient() {
            const slice = this.slice();

            const stops = this.segments.map(
                (seado, index) => `${this.color(index)} ${index * slice}deg ${(index + 1) * slice}deg`
            );

            return `conic-gradient(from 0deg, ${stops.join(', ')})`;
        },

        // Labels run from the hub outward along the middle of each slice.
        labelAngle(index) {
            return index * this.slice() + this.slice() / 2 - 90;
        },

        shortName(name) {
            const words = name.split(/\s+/).filter((word) => word.replace(/\./g, '') !== '');

            return words.length <= 2 ? words.join(' ') : `${words[0]} ${words[words.length - 1]}`;
        },

        initials(name) {
            const words = name.split(/\s+/).filter((word) => /[a-z]/i.test(word));

            return ((words[0]?.[0] ?? '') + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
        },

        spin() {
            if (this.phase !== 'idle' || this.segments.length === 0) {
                return;
            }

            this.winner = null;
            this.showResult = false;
            this.phase = 'shuffling';

            // Keep the wheel still while the names are shuffled.
            this.transition = 'none';

            let ticks = 0;

            const shuffle = setInterval(() => {
                this.segments = shuffled(this.segments);
                this.highlight = this.segments[randomInt(this.segments.length)].name;

                if (++ticks >= SHUFFLE_TICKS) {
                    clearInterval(shuffle);
                    this.$nextTick(() => requestAnimationFrame(() => this.startSpin()));
                }
            }, SHUFFLE_MS);
        },

        startSpin() {
            const slice = this.slice();

            this.winnerIndex = randomInt(this.segments.length);

            // Land somewhere inside the winning slice, not always dead centre.
            const target = this.winnerIndex * slice + slice / 2 + (Math.random() - 0.5) * slice * 0.6;

            const current = ((this.rotation % 360) + 360) % 360;

            const delta = (((360 - target - current) % 360) + 360) % 360;

            this.phase = 'spinning';
            this.transition = `transform ${SPIN_MS}ms cubic-bezier(0.12, 0.8, 0.1, 1)`;
            this.rotation += FULL_TURNS * 360 + delta;

            this.trackPointer();

            // transitionend can be skipped (hidden tab); finish regardless.
            this.fallbackTimer = setTimeout(() => this.finish(), SPIN_MS + 400);
        },

        // Light up whichever SEADO is passing under the pointer.
        trackPointer() {
            if (this.phase !== 'spinning') {
                return;
            }

            const matrix = getComputedStyle(this.$refs.wheel).transform;

            if (matrix && matrix !== 'none') {
                const [a, b] = matrix.match(/matrix\(([^)]+)\)/)[1].split(',').map(Number);

                const angle = ((Math.atan2(b, a) * 180) / Math.PI + 360) % 360;

                const underPointer = Math.floor(((360 - angle) % 360) / this.slice());

                this.highlight = this.segments[underPointer % this.segments.length]?.name ?? null;
            }

            requestAnimationFrame(() => this.trackPointer());
        },

        finish() {
            if (this.phase !== 'spinning') {
                return;
            }

            clearTimeout(this.fallbackTimer);

            this.phase = 'idle';
            this.winner = this.segments[this.winnerIndex];
            this.highlight = this.winner.name;
            this.showResult = true;
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