<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
} from 'chart.js'
import { useDarkMode } from '@/composables/useDarkMode'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const { isDark } = useDarkMode()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.date?.substring(5, 10) || d.date),
  datasets: [
    {
      label: 'Impressions',
      data: props.data.map((d) => Number(d.impressions)),
      backgroundColor: isDark.value ? 'rgba(139, 92, 246, 0.6)' : 'rgba(139, 92, 246, 0.5)',
      hoverBackgroundColor: '#8B5CF6',
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
    tooltip: {
      backgroundColor: isDark.value ? '#1c1c1c' : '#fff',
      titleColor: isDark.value ? '#fff' : '#18181b',
      bodyColor: isDark.value ? '#a1a1aa' : '#71717a',
      borderColor: isDark.value ? '#333' : '#e4e4e7',
      borderWidth: 1,
      padding: 12,
      cornerRadius: 12,
      displayColors: false,
      callbacks: {
        label: (ctx) => new Intl.NumberFormat('id-ID').format(ctx.raw) + ' impressions',
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: isDark.value ? '#555' : '#a1a1aa', font: { size: 10 }, maxRotation: 0 },
      border: { display: false },
    },
    y: {
      grid: { color: isDark.value ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)' },
      ticks: {
        color: isDark.value ? '#555' : '#a1a1aa',
        font: { size: 10 },
        callback: (v) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v),
      },
      border: { display: false },
    },
  },
}))
</script>

<template>
  <div class="h-[220px] w-full">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
