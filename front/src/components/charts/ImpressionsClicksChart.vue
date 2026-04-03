<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { useDarkMode } from '@/composables/useDarkMode'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const { isDark } = useDarkMode()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.date?.substring(0, 10) || d.date),
  datasets: [
    {
      label: 'Impressions',
      data: props.data.map((d) => Number(d.impressions)),
      backgroundColor: isDark.value ? 'rgba(139, 92, 246, 0.6)' : 'rgba(139, 92, 246, 0.5)',
      hoverBackgroundColor: '#8B5CF6',
      borderRadius: 6,
      borderSkipped: false,
    },
    {
      label: 'Clicks',
      data: props.data.map((d) => Number(d.clicks)),
      backgroundColor: isDark.value ? 'rgba(16, 185, 129, 0.7)' : 'rgba(16, 185, 129, 0.6)',
      hoverBackgroundColor: '#10B981',
      borderRadius: 6,
      borderSkipped: false,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: 'index' },
  plugins: {
    legend: {
      display: true,
      position: 'top',
      align: 'end',
      labels: {
        color: isDark.value ? '#888' : '#71717a',
        font: { size: 11 },
        boxWidth: 12,
        boxHeight: 12,
        borderRadius: 3,
        useBorderRadius: true,
        padding: 12,
      },
    },
    tooltip: {
      backgroundColor: isDark.value ? '#1c1c1c' : '#fff',
      titleColor: isDark.value ? '#fff' : '#18181b',
      bodyColor: isDark.value ? '#a1a1aa' : '#71717a',
      borderColor: isDark.value ? '#333' : '#e4e4e7',
      borderWidth: 1,
      padding: 12,
      cornerRadius: 12,
      callbacks: {
        label: (ctx) => ctx.dataset.label + ': ' + new Intl.NumberFormat('id-ID').format(ctx.raw),
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: isDark.value ? '#555' : '#a1a1aa', font: { size: 11 }, maxRotation: 0 },
      border: { display: false },
    },
    y: {
      grid: { color: isDark.value ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)' },
      ticks: {
        color: isDark.value ? '#555' : '#a1a1aa',
        font: { size: 11 },
        callback: (v) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v),
      },
      border: { display: false },
    },
  },
}))
</script>

<template>
  <div class="h-[260px] w-full">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
