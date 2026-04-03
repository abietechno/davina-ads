<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
} from 'chart.js'
import { useDarkMode } from '@/composables/useDarkMode'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const { isDark } = useDarkMode()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.date?.substring(5, 10) || d.date),
  datasets: [
    {
      label: 'Clicks',
      data: props.data.map((d) => Number(d.clicks)),
      borderColor: '#10B981',
      backgroundColor: (ctx) => {
        const chart = ctx.chart
        const gradient = chart.ctx.createLinearGradient(0, 0, 0, 220)
        gradient.addColorStop(0, isDark.value ? 'rgba(16,185,129,0.25)' : 'rgba(16,185,129,0.15)')
        gradient.addColorStop(1, 'rgba(16,185,129,0)')
        return gradient
      },
      borderWidth: 2.5,
      pointRadius: props.data.length > 14 ? 0 : 4,
      pointHoverRadius: 6,
      pointBackgroundColor: '#10B981',
      pointBorderColor: isDark.value ? '#000' : '#fff',
      pointBorderWidth: 2,
      fill: true,
      tension: 0.4,
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
        label: (ctx) => new Intl.NumberFormat('id-ID').format(ctx.raw) + ' clicks',
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
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
