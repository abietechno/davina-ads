<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { useDarkMode } from '@/composables/useDarkMode'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  data: { type: Array, default: () => [] },
  labelKey: { type: String, default: 'name' },
  valueKey: { type: String, default: 'spend' },
})

const { isDark } = useDarkMode()

const palette = [
  '#3B82F6', // blue
  '#8B5CF6', // violet
  '#10B981', // emerald
  '#F59E0B', // amber
  '#EF4444', // red
  '#EC4899', // pink
  '#06B6D4', // cyan
  '#F97316', // orange
  '#6366F1', // indigo
  '#14B8A6', // teal
]

const chartData = computed(() => ({
  labels: props.data.map((d) => {
    const name = d[props.labelKey] || 'Unknown'
    return name.length > 35 ? name.substring(0, 35) + '…' : name
  }),
  datasets: [
    {
      data: props.data.map((d) => Number(d[props.valueKey])),
      backgroundColor: props.data.map((_, i) => {
        const base = palette[i % palette.length]
        return isDark.value ? base : base + 'cc' // slight transparency in light
      }),
      borderColor: isDark.value ? '#000' : '#fff',
      borderWidth: 2,
      hoverOffset: 8,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: {
      display: true,
      position: 'right',
      labels: {
        color: isDark.value ? '#999' : '#71717a',
        font: { size: 11 },
        boxWidth: 14,
        boxHeight: 14,
        borderRadius: 4,
        useBorderRadius: true,
        padding: 10,
        generateLabels: (chart) => {
          const data = chart.data
          return data.labels.map((label, i) => ({
            text: label.length > 20 ? label.substring(0, 20) + '…' : label,
            fillStyle: data.datasets[0].backgroundColor[i],
            strokeStyle: 'transparent',
            index: i,
            hidden: false,
          }))
        },
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
      displayColors: true,
      callbacks: {
        label: (ctx) => ' Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw),
      },
    },
  },
}))
</script>

<template>
  <div class="h-[280px] w-full flex items-center justify-center">
    <Doughnut v-if="data.length > 0" :data="chartData" :options="chartOptions" />
    <p v-else class="text-sm text-muted-foreground">No data</p>
  </div>
</template>
