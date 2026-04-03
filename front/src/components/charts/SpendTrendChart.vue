<script setup>
import { computed, ref, onMounted } from 'vue'
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
const canvasRef = ref(null)

// Create gradient programmatically
function createGradient(ctx) {
  const gradient = ctx.createLinearGradient(0, 0, 0, 260)
  if (isDark.value) {
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)')
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0)')
  } else {
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.15)')
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0)')
  }
  return gradient
}

const chartData = computed(() => ({
  labels: props.data.map((d) => d.date?.substring(0, 10) || d.date),
  datasets: [
    {
      label: 'Spend (Rp)',
      data: props.data.map((d) => Number(d.spend)),
      borderColor: '#3B82F6',
      backgroundColor: (ctx) => {
        const chart = ctx.chart
        const { ctx: canvasCtx } = chart
        return createGradient(canvasCtx)
      },
      borderWidth: 2.5,
      pointRadius: props.data.length > 14 ? 0 : 5,
      pointHoverRadius: 7,
      pointBackgroundColor: '#3B82F6',
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
        label: (ctx) => 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw),
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
        callback: (v) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v),
      },
      border: { display: false },
    },
  },
}))
</script>

<template>
  <div class="h-[260px] w-full">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
