<script setup lang="ts">
import { Chart, registerables, type ChartConfiguration } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

const props = withDefaults(
    defineProps<{
        type: 'bar' | 'line' | 'doughnut';
        labels: string[];
        series: { label: string; data: number[]; color: string }[];
        divisor?: number;
        fill?: boolean;
    }>(),
    { divisor: 100, fill: true },
);

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

function render(): void {
    if (!canvas.value || props.labels.length === 0) {
        chart?.destroy();
        chart = null;
        return;
    }

    const dark = document.documentElement.classList.contains('dark');
    const tick = dark ? '#d6d3d1' : '#57534e';
    const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(28,25,23,0.08)';

    chart?.destroy();
    const config: ChartConfiguration = {
        type: props.type,
        data: {
            labels: props.labels,
            datasets: props.series.map((item) => ({
                label: item.label,
                data: item.data.map((value) => value / props.divisor),
                borderColor: item.color,
                backgroundColor: props.type === 'doughnut'
                    ? item.data.map((_, index) => ['#0f766e', '#b45309', '#9f1239', '#44403c', '#0369a1', '#65a30d', '#7c3aed', '#a16207'][index % 8])
                    : props.type === 'line' ? `${item.color}33` : item.color,
                tension: 0.25,
                fill: props.type === 'line' && props.fill,
                pointRadius: props.type === 'line' && item.data.length > 40 ? 0 : 3,
                borderWidth: 2,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: tick } },
            },
            scales: props.type === 'doughnut'
                ? undefined
                : {
                    x: { ticks: { color: tick }, grid: { color: grid } },
                    y: { ticks: { color: tick }, grid: { color: grid } },
                },
        },
    };
    chart = new Chart(canvas.value, config);
}

onMounted(render);
watch(() => [props.labels, props.series, props.type], render, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div class="h-64">
        <canvas ref="canvas" />
    </div>
</template>
