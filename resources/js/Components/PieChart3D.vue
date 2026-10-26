<script setup>
import { computed, ref } from 'vue';

/**
 * A tilted ("3D") pie drawn in plain SVG, with a hover tooltip and a legend that doubles
 * as the accessible table of values. Hovering a slice or its legend row highlights both.
 */
const props = defineProps({
    items: { type: Array, required: true }, // [{ label, value }]
    unit: { type: String, default: 'item' }, // singular, e.g. "employee"
    // Beyond this many slices the smallest fold into "Other", so hues stay distinguishable.
    maxSlices: { type: Number, default: 8 },
});

// Categorical slots in fixed order (validated palette); "Other" is a neutral gray, never a hue.
const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
const OTHER = '#9ca3af';

const W = 320;
const CX = W / 2;
const R = 140;
const TILT = 0.55;
const RY = R * TILT;
const DEPTH = 26;
const CY = RY + 14;
const H = CY + RY + DEPTH + 14;
const LIFT = 12;

const total = computed(() => props.items.reduce((sum, i) => sum + Number(i.value || 0), 0));

const groups = computed(() => {
    const sorted = props.items.filter((i) => Number(i.value) > 0).sort((a, b) => b.value - a.value);
    if (sorted.length <= props.maxSlices) {
        return sorted.map((i, n) => ({ ...i, color: PALETTE[n] }));
    }
    const kept = sorted.slice(0, props.maxSlices - 1).map((i, n) => ({ ...i, color: PALETTE[n] }));
    const rest = sorted.slice(props.maxSlices - 1);
    return [
        ...kept,
        {
            label: 'Other',
            value: rest.reduce((s, i) => s + i.value, 0),
            color: OTHER,
            detail: rest.map((i) => i.label).join(', '),
        },
    ];
});

const point = (a, dy = 0) => [CX + R * Math.cos(a), CY + RY * Math.sin(a) + dy];
const fmt = ([x, y]) => `${x.toFixed(2)} ${y.toFixed(2)}`;

function darken(hex, factor = 0.72) {
    const n = parseInt(hex.slice(1), 16);
    const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => Math.round(v * factor));
    return `rgb(${c.join(',')})`;
}

const slices = computed(() => {
    let start = -Math.PI / 2;
    return groups.value.map((g) => {
        const sweep = (g.value / total.value) * Math.PI * 2;
        const end = start + sweep;
        const mid = start + sweep / 2;
        const whole = sweep >= Math.PI * 2 - 1e-6;

        const top = whole
            ? `M ${fmt(point(0))} A ${R} ${RY} 0 1 1 ${fmt(point(Math.PI))} A ${R} ${RY} 0 1 1 ${fmt(point(0))} Z`
            : `M ${CX} ${CY} L ${fmt(point(start))} A ${R} ${RY} 0 ${sweep > Math.PI ? 1 : 0} 1 ${fmt(point(end))} Z`;

        // Only the front half of the rim (screen-bottom, angles 0..π) is visible as a wall.
        const s = Math.max(start, 0);
        const e = Math.min(end, Math.PI);
        const wall = s < e
            ? `M ${fmt(point(s))} A ${R} ${RY} 0 0 1 ${fmt(point(e))} L ${fmt(point(e, DEPTH))} A ${R} ${RY} 0 0 0 ${fmt(point(s, DEPTH))} Z`
            : null;

        const slice = {
            ...g,
            top,
            wall,
            share: g.value / total.value,
            lift: whole ? [0, -LIFT / 2] : [Math.cos(mid) * LIFT, Math.sin(mid) * LIFT * TILT],
        };
        start = end;
        return slice;
    });
});

const active = ref(null);
const tooltip = ref({ x: 0, y: 0 });
const root = ref(null);

// Legend highlights have no pointer position, so they show no floating tooltip.
function highlight(index) {
    active.value = index;
    tooltip.value = { x: 0, y: 0 };
}

function onMove(event, index) {
    active.value = index;
    const box = root.value.getBoundingClientRect();
    tooltip.value = { x: event.clientX - box.left, y: event.clientY - box.top };
}

// Draw the highlighted slice last so its lifted wall sits on top of its neighbours.
const drawOrder = computed(() => {
    const order = slices.value.map((_, i) => i);
    if (active.value === null) return order;
    return [...order.filter((i) => i !== active.value), active.value];
});

const pct = (share) => `${(share * 100).toLocaleString('en-KE', { maximumFractionDigits: 1 })}%`;
const count = (n) => `${Number(n).toLocaleString('en-KE')} ${props.unit}${Number(n) === 1 ? '' : 's'}`;

const activeSlice = computed(() => (active.value === null ? null : slices.value[active.value]));
</script>

<template>
    <div ref="root" class="relative">
        <p v-if="!total" class="text-sm text-gray-500">No data.</p>

        <div v-else class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="w-full max-w-[20rem] shrink-0 select-none overflow-visible"
                role="img"
                :aria-label="`Pie chart of ${count(total)}; values are listed in the legend.`"
                @mouseleave="highlight(null)"
            >
                <ellipse :cx="CX" :cy="CY + DEPTH + 6" :rx="R * 0.92" :ry="RY * 0.75" fill="#000" opacity="0.06" />

                <g
                    v-for="i in drawOrder"
                    :key="slices[i].label"
                    :transform="active === i ? `translate(${slices[i].lift[0]} ${slices[i].lift[1]})` : null"
                    :class="active !== null && active !== i ? 'saturate-[.35] brightness-[1.12]' : ''"
                    class="cursor-pointer transition-[filter,transform] duration-150 ease-out"
                    @mousemove="onMove($event, i)"
                >
                    <path v-if="slices[i].wall" :d="slices[i].wall" :fill="darken(slices[i].color)" stroke="#fff" stroke-width="1.5" stroke-linejoin="round" />
                    <path :d="slices[i].top" :fill="slices[i].color" stroke="#fff" stroke-width="2" stroke-linejoin="round" />
                </g>
            </svg>

            <!-- Legend: also the readable table of values -->
            <ul class="w-full min-w-0 flex-1 space-y-0.5">
                <li v-for="(s, i) in slices" :key="s.label">
                    <button
                        type="button"
                        @mouseenter="highlight(i)"
                        @mouseleave="highlight(null)"
                        @focus="highlight(i)"
                        @blur="highlight(null)"
                        :class="active === i ? 'bg-gray-100' : ''"
                        class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm"
                        :title="s.detail"
                    >
                        <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: s.color }" />
                        <span class="min-w-0 flex-1 truncate text-gray-700">{{ s.label }}</span>
                        <span class="font-medium tabular-nums text-gray-900">{{ Number(s.value).toLocaleString('en-KE') }}</span>
                        <span class="w-12 text-right text-xs tabular-nums text-gray-500">{{ pct(s.share) }}</span>
                    </button>
                </li>
                <li class="flex items-center justify-between border-t border-gray-100 px-2 pt-2 text-sm">
                    <span class="font-medium text-gray-700">Total</span>
                    <span class="font-semibold tabular-nums text-gray-900">{{ count(total) }}</span>
                </li>
            </ul>
        </div>

        <!-- Hover tooltip (pointer only; keyboard users get the legend highlight) -->
        <div
            v-if="activeSlice && tooltip.x"
            class="pointer-events-none absolute z-10 w-max max-w-[14rem] -translate-x-1/2 -translate-y-[calc(100%+12px)] rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-lg"
            :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
        >
            <p class="flex items-center gap-2 font-semibold text-gray-900">
                <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: activeSlice.color }" />
                {{ activeSlice.label }}
            </p>
            <p class="mt-0.5 tabular-nums text-gray-700">{{ count(activeSlice.value) }} · {{ pct(activeSlice.share) }}</p>
            <p v-if="activeSlice.detail" class="mt-1 text-xs text-gray-500">{{ activeSlice.detail }}</p>
        </div>
    </div>
</template>
