<script setup>
import { ref, computed } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

// Dates are 'YYYY-MM-DD' strings in local time everywhere: string order = date order, and nothing
// goes through toISOString() (UTC would still be "yesterday" in WIB/WITA until 07:00/08:00).
const start = defineModel('start', { type: String, required: true })
const end = defineModel('end', { type: String, required: true })

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const open = ref(false)
const draftStart = ref(null)
const draftEnd = ref(null) // null while the second click is pending
const hoverKey = ref(null)
// First day of the right-hand month; the left month is the one before it (hidden on phones)
const anchor = ref(firstOfMonth(new Date()))

function toKey(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function parseKey(key) {
  const [y, m, d] = key.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function addDays(key, n) {
  const d = parseKey(key)
  d.setDate(d.getDate() + n)
  return toKey(d)
}

function firstOfMonth(d) {
  return new Date(d.getFullYear(), d.getMonth(), 1)
}

function addMonths(d, n) {
  return new Date(d.getFullYear(), d.getMonth() + n, 1)
}

const today = () => toKey(new Date())

const PRESETS = [
  { label: 'Today', range: () => [today(), today()] },
  { label: 'Yesterday', range: () => [addDays(today(), -1), addDays(today(), -1)] },
  { label: 'Last 7 Days', range: () => [addDays(today(), -6), today()] },
  { label: 'Last 30 Days', range: () => [addDays(today(), -29), today()] },
  { label: 'Last 90 Days', range: () => [addDays(today(), -89), today()] },
  { label: 'This Month', range: () => [toKey(firstOfMonth(new Date())), today()] },
  {
    label: 'Last Month',
    range: () => {
      const first = addMonths(firstOfMonth(new Date()), -1)
      return [toKey(first), addDays(toKey(addMonths(first, 1)), -1)]
    },
  },
]

const dayFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
const dayMonthFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' })
const monthFormat = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })

// "12 Nov 2026", "1 – 31 Oct 2026", "14 Oct – 12 Nov 2026", "20 Dec 2025 – 5 Jan 2026"
function formatRange(a, b) {
  if (!a) return ''
  if (!b || a === b) return dayFormat.format(parseKey(a))
  const from = a.slice(0, 7) === b.slice(0, 7)
    ? String(parseKey(a).getDate())
    : (a.slice(0, 4) === b.slice(0, 4) ? dayMonthFormat : dayFormat).format(parseKey(a))
  return `${from} – ${dayFormat.format(parseKey(b))}`
}

function dayCount(a, b) {
  return Math.round((parseKey(b) - parseKey(a)) / 86_400_000) + 1
}

// Range shown in the calendar: the draft, or start..hover while choosing the end date
const shownRange = computed(() => {
  if (!draftStart.value) return null
  const other = draftEnd.value ?? hoverKey.value ?? draftStart.value
  return draftStart.value <= other ? [draftStart.value, other] : [other, draftStart.value]
})

const activePreset = computed(() => {
  if (!draftStart.value || !draftEnd.value) return null
  return PRESETS.find((p) => {
    const [a, b] = p.range()
    return a === draftStart.value && b === draftEnd.value
  })?.label
})

const canGoNext = computed(() => anchor.value < firstOfMonth(new Date()))

const months = computed(() =>
  [addMonths(anchor.value, -1), anchor.value].map((first) => {
    const offset = (first.getDay() + 6) % 7 // Monday-first week
    const lastDay = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate()
    const todayKey = today()
    const [lo, hi] = shownRange.value ?? []
    const days = Array.from({ length: 42 }, (_, i) => {
      const date = new Date(first.getFullYear(), first.getMonth(), 1 - offset + i)
      const key = toKey(date)
      const inMonth = date.getMonth() === first.getMonth()
      const inRange = inMonth && !!lo && key >= lo && key <= hi
      return {
        key,
        day: date.getDate(),
        inMonth,
        disabled: key > todayKey, // no ad data in the future
        isToday: inMonth && key === todayKey,
        isEndpoint: inMonth && (key === lo || key === hi),
        // The band behind the range; rounded where it breaks (range ends, week rows, month edges)
        band: inRange && lo !== hi,
        roundLeft: key === lo || i % 7 === 0 || date.getDate() === 1,
        roundRight: key === hi || i % 7 === 6 || date.getDate() === lastDay,
      }
    })
    return { key: toKey(first), title: monthFormat.format(first), days }
  }),
)

const summary = computed(() => {
  if (!draftStart.value) return 'Select a start date'
  if (!draftEnd.value) return `${formatRange(draftStart.value)} – select an end date`
  return `${formatRange(draftStart.value, draftEnd.value)} · ${dayCount(draftStart.value, draftEnd.value)} days`
})

function onOpenChange(value) {
  open.value = value
  if (!value) return
  draftStart.value = start.value
  draftEnd.value = end.value
  hoverKey.value = null
  anchor.value = firstOfMonth(parseKey(end.value || today()))
}

function pick(day) {
  if (day.disabled || !day.inMonth) return
  if (!draftStart.value || draftEnd.value) {
    draftStart.value = day.key
    draftEnd.value = null
  } else if (day.key < draftStart.value) {
    draftEnd.value = draftStart.value
    draftStart.value = day.key
  } else {
    draftEnd.value = day.key
  }
}

function applyPreset(preset) {
  ;[draftStart.value, draftEnd.value] = preset.range()
  apply()
}

function apply() {
  if (!draftStart.value) return
  start.value = draftStart.value
  end.value = draftEnd.value ?? draftStart.value // one click = a single day
  open.value = false
}
</script>

<template>
  <Popover :open="open" @update:open="onOpenChange">
    <PopoverTrigger as-child>
      <Button variant="outline" class="w-full justify-start gap-2 rounded-xl font-normal sm:w-auto">
        <CalendarDays class="h-4 w-4 text-muted-foreground" />
        <span class="text-sm">{{ formatRange(start, end) }}</span>
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-auto max-w-[calc(100vw-1rem)] rounded-2xl p-0" align="start">
      <div class="flex flex-col sm:flex-row">
        <!-- Presets: a scrollable row on phones, a side list on wider screens -->
        <div class="flex gap-1 overflow-x-auto border-b border-border/60 p-2 [scrollbar-width:none] sm:w-36 sm:flex-col sm:overflow-visible sm:border-b-0 sm:border-r [&::-webkit-scrollbar]:hidden">
          <button
            v-for="preset in PRESETS"
            :key="preset.label"
            type="button"
            :class="[
              'shrink-0 whitespace-nowrap rounded-lg px-3 py-1.5 text-left text-xs transition-colors sm:text-sm',
              activePreset === preset.label ? 'bg-foreground text-background' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            ]"
            @click="applyPreset(preset)"
          >
            {{ preset.label }}
          </button>
        </div>

        <div class="p-3">
          <div class="relative flex gap-6">
            <button
              type="button"
              class="absolute left-0 top-0 flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
              aria-label="Previous month"
              @click="anchor = addMonths(anchor, -1)"
            >
              <ChevronLeft class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-30"
              aria-label="Next month"
              :disabled="!canGoNext"
              @click="anchor = addMonths(anchor, 1)"
            >
              <ChevronRight class="h-4 w-4" />
            </button>

            <div v-for="(month, idx) in months" :key="month.key" :class="idx === 0 && 'hidden sm:block'">
              <p class="mb-2 h-8 text-center text-sm font-semibold leading-8">{{ month.title }}</p>
              <div class="grid grid-cols-7 text-center text-[11px] font-medium text-muted-foreground">
                <span v-for="w in WEEKDAYS" :key="w" class="pb-1">{{ w }}</span>
              </div>
              <div class="grid grid-cols-7" @mouseleave="hoverKey = null">
                <div
                  v-for="day in month.days"
                  :key="day.key"
                  :class="[
                    'my-0.5',
                    day.band && 'bg-muted',
                    day.band && day.roundLeft && 'rounded-l-lg',
                    day.band && day.roundRight && 'rounded-r-lg',
                  ]"
                >
                  <button
                    type="button"
                    :disabled="day.disabled || !day.inMonth"
                    :class="[
                      'mx-auto flex h-8 w-9 items-center justify-center rounded-lg text-sm tabular-nums transition-colors sm:w-10',
                      !day.inMonth && 'text-muted-foreground/30',
                      day.inMonth && day.disabled && 'text-muted-foreground/40',
                      day.inMonth && !day.disabled && !day.isEndpoint && 'hover:bg-foreground/10',
                      day.isEndpoint && 'bg-foreground font-semibold text-background',
                      day.isToday && !day.isEndpoint && 'font-semibold underline underline-offset-4',
                    ]"
                    @click="pick(day)"
                    @mouseenter="day.inMonth && !day.disabled && (hoverKey = day.key)"
                  >
                    {{ day.day }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-3">
            <span class="text-xs text-muted-foreground">{{ summary }}</span>
            <div class="flex gap-2">
              <Button variant="outline" size="sm" class="rounded-lg" @click="open = false">Cancel</Button>
              <Button size="sm" class="rounded-lg" :disabled="!draftStart" @click="apply">Apply</Button>
            </div>
          </div>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
