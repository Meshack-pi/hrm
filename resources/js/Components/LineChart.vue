<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A responsive multi-series line chart in plain SVG, with a hover crosshair + tooltip.
 * Series share one y-axis, so only plot measures of the same unit together.
 */
const props = defineProps({
    labels: { type: Array, required: true }, // x-axis categories, e.g. ['Jan 2026', ...]
    series: { type: Array, required: true }, // [{ name, values: Number[], color }]
    format: { type: Function, default: (v) => Number(v).toLocaleString('en-KE') },
    height: { type: Number, default: 240 },
    area: { type: Boolean, default: false }, // soft fill under a single series
});

const PAD = { top: 12, right: 16, bottom: 28, left: 56 };

const root = ref(null);
const width = ref(600);
let observer;

onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        width.value = Math.max(280, entry.contentRect.width);
    });
    observer.observe(root.value);
});

onBeforeUnmount(() => observer?.disconnect());

const plotW = computed(() => width.value - PAD.left - PAD.right);
const plotH = computed(() => props.height - PAD.top - PAD.bottom);

const hasData = computed(() => props.labels.length > 0 && props.series.some((s) => s.values.some((v) => Number(v) > 0)));

// Round the axis max up to a "nice" number so gridlines land on readable values.
const yMax = computed(() => {
    const max = Math.max(0, ...props.series.flatMap((s) => s.values.map(Number)));
    if (max === 0) return 1;
    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = [1, 2, 2.5, 5, 10].find((m) => m * magnitude * 4 >= max) * magnitude;
    return step * 4;
});

const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (yMax.value / 4) * i));

const x = (i) => PAD.left + (props.labels.length === 1 ? plotW.value / 2 : (plotW.value / (props.labels.length - 1)) * i);
const y = (v) => PAD.top + plotH.value - (Number(v) / yMax.value) * plotH.value;

const paths = computed(() =>
    props.series.map((s) => {
        const line = s.values.map((v, i) => `${i ? 'L' : 'M'} ${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' ');
        const base = (PAD.top + plotH.value).toFixed(1);
        return {
            ...s,
            line,
            fill: `${line} L ${x(s.values.length - 1).toFixed(1)} ${base} L ${x(0).toFixed(1)} ${base} Z`,
        };
    }),
);

// Thin x labels so they never collide: roughly one per 80px.
const labelEvery = computed(() => Math.max(1, Math.ceil(props.labels.length / Math.floor(plotW.value / 80))));

const compact = (v) => Number(v).toLocaleString('en-KE', { notation: 'compact', maximumFractionDigits: 1 });

const active = ref(null);

function onMove(event) {
    const box = event.currentTarget.getBoundingClientRect();
    const px = ((event.clientX - box.left) / box.width) * width.value;
    const step = props.labels.length > 1 ? plotW.value / (props.labels.length - 1) : plotW.value;
    active.value = Math.min(props.labels.length - 1, Math.max(0, Math.round((px - PAD.left) / step)));
}

const tooltipLeft = computed(() => {
    if (active.value === null) return 0;
    return Math.min(Math.max(x(active.value), 90), width.value - 90);
});
</script>

<template>
    <div ref="root" class="relative w-full">
        <p v-if="!hasData" class="flex items-center justify-center text-sm text-gray-500" :style="{ height: `${height}px` }">
            No data yet.
        </p>

        <template v-else>
            <ul v-if="series.length > 1" class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                <li v-for="s in series" :key="s.name" class="flex items-center gap-1.5">
                    <span class="h-0.5 w-4 rounded-full" :style="{ backgroundColor: s.color }" />
                    {{ s.name }}
                </li>
            </ul>

            <svg
                :viewBox="`0 0 ${width} ${height}`"
                :height="height"
                class="w-full select-none overflow-visible"
                role="img"
                :aria-label="`Line chart of ${series.map((s) => s.name).join(' and ')}; values are in the table below.`"
                @mousemove="onMove"
                @mouseleave="active = null"
            >
                <g class="text-[11px]" fill="#6b7280">
                    <template v-for="t in ticks" :key="t">
                        <line :x1="PAD.left" :x2="width - PAD.right" :y1="y(t)" :y2="y(t)" :stroke="t === 0 ? '#d1d5db' : '#f3f4f6'" />
                        <text :x="PAD.left - 8" :y="y(t)" text-anchor="end" dominant-baseline="middle">{{ compact(t) }}</text>
                    </template>
                    <template v-for="(label, i) in labels" :key="label">
                        <text v-if="i % labelEvery === 0 || i === labels.length - 1" :x="x(i)" :y="height - 8" text-anchor="middle">
                            {{ label }}
                        </text>
                    </template>
                </g>

                <line
                    v-if="active !== null"
                    :x1="x(active)"
                    :x2="x(active)"
                    :y1="PAD.top"
                    :y2="PAD.top + plotH"
                    stroke="#9ca3af"
                    stroke-dasharray="3 3"
                />

                <g v-for="p in paths" :key="p.name">
                    <path v-if="area && series.length === 1" :d="p.fill" :fill="p.color" opacity="0.08" />
                    <path :d="p.line" fill="none" :stroke="p.color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    <circle
                        v-if="active !== null"
                        :cx="x(active)"
                        :cy="y(p.values[active])"
                        r="4.5"
                        :fill="p.color"
                        stroke="#fff"
                        stroke-width="2"
                    />
                    <circle v-if="labels.length === 1" :cx="x(0)" :cy="y(p.values[0])" r="4" :fill="p.color" />
                </g>
            </svg>

            <div
                v-if="active !== null"
                class="pointer-events-none absolute z-10 w-max -translate-x-1/2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-lg"
                :style="{ left: `${tooltipLeft}px`, top: series.length > 1 ? '1.75rem' : '0' }"
            >
                <p class="font-semibold text-gray-900">{{ labels[active] }}</p>
                <p v-for="s in series" :key="s.name" class="mt-0.5 flex items-center gap-2 tabular-nums text-gray-700">
                    <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: s.color }" />
                    <span v-if="series.length > 1" class="text-gray-500">{{ s.name }}</span>
                    <span class="font-medium text-gray-900">{{ format(s.values[active]) }}</span>
                </p>
            </div>

            <table class="sr-only">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th v-for="s in series" :key="s.name">{{ s.name }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(label, i) in labels" :key="label">
                        <td>{{ label }}</td>
                        <td v-for="s in series" :key="s.name">{{ format(s.values[i]) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </div>
</template>
