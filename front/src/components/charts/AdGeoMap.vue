<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
  geo: { type: Object, required: true },
  platform: { type: String, default: '' },
  conversionLabel: { type: String, default: 'Conv.' },
})

const PLATFORM_COLORS = { meta: '#0866FF', google: '#34A853' }
const ABROAD_COLOR = '#F59E0B'
// Only the biggest spots pulse: hundreds of animated rings would be noise (and heavy on phones)
const PULSING_POINTS = 25
const VIEWS = {
  indonesia: { label: 'Indonesia', bounds: [[-11.2, 94.8], [6.3, 141.1]] },
  bali: { label: 'Bali', bounds: [[-8.86, 114.42], [-8.05, 115.72]] },
  world: { label: 'Dunia', bounds: null },
}

const mapEl = ref(null)
const activeView = ref('indonesia')
let map = null
let markerLayer = null
let resizeObserver = null
const markersByKey = new Map()

const countryNames = new Intl.DisplayNames(['id'], { type: 'region' })

const points = computed(() => props.geo?.points || [])
const mappedSpend = computed(() => (props.geo?.domestic_spend || 0) + (props.geo?.abroad_spend || 0))
const totalSpend = computed(() => mappedSpend.value + (props.geo?.unmapped_spend || 0))
const hasAbroad = computed(() => points.value.some((p) => p.scope === 'abroad'))
const topPoints = computed(() => points.value.slice(0, 8))
const availableViews = computed(() =>
  Object.entries(VIEWS).filter(([key]) => key !== 'world' || hasAbroad.value),
)
const footnote = computed(() =>
  [
    'Titik = wilayah tempat audiens berada saat iklan tayang (bukan GPS persis), ukuran sesuai spend.',
    props.platform === 'meta' && 'Meta hanya melaporkan sampai provinsi.',
    props.geo?.conversions_approximate &&
      `${props.conversionLabel} per wilayah memakai "messaging connection", karena Meta tidak memecah "conversation started" per wilayah.`,
  ]
    .filter(Boolean)
    .join(' '),
)

function labelOf(point) {
  return point.scope === 'abroad' ? safeCountryName(point.country_code) : point.label
}

function sublabelOf(point) {
  if (point.scope === 'abroad') return 'Luar negeri'
  return point.precision === 'city' ? point.region : 'Provinsi'
}

function safeCountryName(code) {
  try {
    return countryNames.of(code) || code
  } catch {
    return code
  }
}

function formatCurrency(v) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function formatCompactCurrency(v) {
  return 'Rp' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 2 }).format(v)
}

function formatNumber(v) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(v)
}

function share(v) {
  if (!totalSpend.value) return 0
  return Math.round((v / totalSpend.value) * 1000) / 10
}

function colorOf(point) {
  return point.scope === 'abroad' ? ABROAD_COLOR : PLATFORM_COLORS[props.platform] || '#6366F1'
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c])
}

function tooltipHtml(point) {
  const approx = props.geo?.conversions_approximate ? ' (perkiraan)' : ''
  return `
    <div class="geo-tip">
      <div class="geo-tip__title">${escapeHtml(labelOf(point))}</div>
      <div class="geo-tip__sub">${escapeHtml(sublabelOf(point))}</div>
      <div class="geo-tip__spend">${formatCurrency(point.spend)} <span>${share(point.spend)}%</span></div>
      <div class="geo-tip__row">
        <span>Impr. ${formatNumber(point.impressions)}</span>
        <span>Klik ${formatNumber(point.clicks)}</span>
        <span>${escapeHtml(props.conversionLabel)} ${formatNumber(point.conversions)}${approx}</span>
      </div>
    </div>`
}

function renderMarkers() {
  if (!map) return
  markerLayer.clearLayers()
  markersByKey.clear()

  const maxSpend = Math.max(...points.value.map((p) => p.spend), 1)

  // Smallest first so big dots end up on top and stay hoverable
  ;[...points.value].reverse().forEach((point, reversedIdx) => {
    const rank = points.value.length - 1 - reversedIdx
    // Area scales with spend (sqrt), clamped so tiny spots stay visible
    const size = Math.round(8 + 24 * Math.sqrt(point.spend / maxSpend))
    const pulse = rank < PULSING_POINTS && point.spend > 0
    const classes = ['geo-dot', pulse && 'geo-dot--pulse', point.precision !== 'city' && 'geo-dot--area']
      .filter(Boolean)
      .join(' ')

    const marker = L.marker([point.lat, point.lng], {
      icon: L.divIcon({
        className: 'geo-dot-icon',
        html: `<span class="${classes}" style="--size:${size}px;--color:${colorOf(point)};--delay:${(rank % 5) * 0.35}s"><span class="geo-dot__ring"></span><span class="geo-dot__ring geo-dot__ring--late"></span><span class="geo-dot__core"></span></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      }),
      riseOnHover: true,
      keyboard: false,
    })

    marker.bindTooltip(tooltipHtml(point), {
      direction: 'top',
      offset: [0, -size / 2],
      className: 'geo-tooltip',
      opacity: 1,
    })
    // Touch screens have no hover
    marker.on('click', () => marker.openTooltip())
    marker.addTo(markerLayer)
    markersByKey.set(point.key, marker)
  })
}

function setView(view, animate = true) {
  activeView.value = view
  if (!map) return

  let bounds = VIEWS[view].bounds
  if (!bounds) {
    bounds = points.value.length ? L.latLngBounds(points.value.map((p) => [p.lat, p.lng])).pad(0.15) : [[-50, -170], [70, 170]]
  }
  map.flyToBounds(bounds, { animate, duration: animate ? 0.8 : 0, padding: [12, 12] })
}

function focusPoint(point) {
  if (!map) return
  const zoom = point.precision === 'city' ? 12 : point.precision === 'region' ? 7 : 4
  map.flyTo([point.lat, point.lng], zoom, { duration: 0.8 })
  map.once('moveend', () => markersByKey.get(point.key)?.openTooltip())
}

onMounted(() => {
  map = L.map(mapEl.value, {
    zoomSnap: 0.25,
    scrollWheelZoom: false, // don't hijack page scrolling; zoom with buttons / pinch
    worldCopyJump: true,
    attributionControl: true,
  })
  // OSM's own tiles: no API key (CARTO now requires one). Fine for a low-traffic internal dashboard;
  // dark mode is a CSS filter on the tile pane rather than a second tile set.
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)
  markerLayer = L.layerGroup().addTo(map)

  renderMarkers()
  const hasDomestic = points.value.some((p) => p.scope === 'domestic')
  setView(hasDomestic || !hasAbroad.value ? 'indonesia' : 'world', false)

  // The card can change width (sidebar, window) after Leaflet measured it
  resizeObserver = new ResizeObserver(() => map?.invalidateSize())
  resizeObserver.observe(mapEl.value)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  map?.remove()
  map = null
})

watch(points, renderMarkers)
</script>

<template>
  <div class="space-y-3">
    <!-- Summary -->
    <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
      <span>
        <span class="mr-1 inline-block h-2 w-2 rounded-full" :style="{ background: PLATFORM_COLORS[platform] }" />
        Indonesia <strong class="text-foreground">{{ formatCompactCurrency(geo.domestic_spend) }}</strong>
        ({{ share(geo.domestic_spend) }}%)
      </span>
      <span v-if="geo.abroad_spend > 0">
        <span class="mr-1 inline-block h-2 w-2 rounded-full" :style="{ background: ABROAD_COLOR }" />
        Luar negeri <strong class="text-foreground">{{ formatCompactCurrency(geo.abroad_spend) }}</strong>
        ({{ share(geo.abroad_spend) }}%)
      </span>
      <span v-if="geo.unmapped_spend > 0" title="Platform tidak melaporkan lokasinya, atau tanggalnya sebelum data lokasi mulai disinkron">
        Tanpa lokasi <strong class="text-foreground">{{ formatCompactCurrency(geo.unmapped_spend) }}</strong>
      </span>
    </div>

    <!-- Abroad warning -->
    <div
      v-if="geo.abroad_spend > 0 && share(geo.abroad_spend) >= 1"
      class="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
    >
      <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
      <span>
        {{ formatCurrency(geo.abroad_spend) }} ({{ share(geo.abroad_spend) }}%) tayang ke orang di luar Indonesia,
        menghasilkan {{ formatNumber(geo.abroad_conversions) }} {{ conversionLabel }}.
        <template v-if="platform === 'google'">
          Cek opsi lokasi kampanye: pilih <em>Presence</em>, bukan <em>Presence or interest</em>.
        </template>
        <template v-else>Cek target lokasi di ad set.</template>
      </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
      <!-- Map -->
      <div class="relative isolate">
        <!-- Class statis: Leaflet menambah class sendiri (leaflet-container, dst.) ke elemen ini.
             Binding :class reaktif membuat Vue menimpa className saat ganti tema, class Leaflet
             hilang, lalu preflight Tailwind (img max-width:100%) mengecilkan tile jadi nol. -->
        <div ref="mapEl" class="geo-map h-[340px] w-full overflow-hidden rounded-xl border border-border/60 sm:h-[420px]" />
        <div class="absolute right-2 top-2 z-[1000] flex gap-0.5 rounded-lg border border-border/60 bg-background/90 p-0.5 shadow-sm backdrop-blur">
          <button
            v-for="[key, view] in availableViews"
            :key="key"
            type="button"
            :class="[
              'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
              activeView === key ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground',
            ]"
            @click="setView(key)"
          >
            {{ view.label }}
          </button>
        </div>
      </div>

      <!-- Top locations -->
      <div class="min-w-0">
        <p class="mb-2 text-xs font-medium text-muted-foreground">Lokasi teratas</p>
        <ul class="space-y-1">
          <li v-for="point in topPoints" :key="point.key">
            <button
              type="button"
              class="w-full rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-muted/60"
              @click="focusPoint(point)"
            >
              <div class="flex items-baseline justify-between gap-2">
                <span class="truncate text-sm font-medium">{{ labelOf(point) }}</span>
                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ share(point.spend) }}%</span>
              </div>
              <div class="flex items-baseline justify-between gap-2 text-xs text-muted-foreground">
                <span class="truncate">{{ sublabelOf(point) }}</span>
                <span class="shrink-0 tabular-nums">{{ formatCompactCurrency(point.spend) }}</span>
              </div>
              <div class="mt-1 h-1 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full" :style="{ width: `${Math.max(share(point.spend), 1)}%`, background: colorOf(point) }" />
              </div>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <p class="text-[11px] leading-relaxed text-muted-foreground/80">{{ footnote }}</p>
  </div>
</template>

<style>
/* Unscoped: Leaflet creates these elements outside Vue's scoped tree */
.geo-map.leaflet-container {
  background: hsl(var(--muted));
  font-family: inherit;
}

/* Muted basemap so the dots carry the colour; dark mode inverts the OSM tiles.
   Dark mode follows the .dark class useDarkMode puts on <html>, so the map element
   itself never needs a reactive class (see the template comment). */
.geo-map .leaflet-tile {
  filter: grayscale(0.85) contrast(0.95) brightness(1.03);
}

.dark .geo-map .leaflet-tile {
  filter: grayscale(1) invert(1) brightness(0.85) contrast(0.9);
}

.geo-map .leaflet-control-attribution {
  font-size: 10px;
  background: hsl(var(--background) / 0.8);
  color: hsl(var(--muted-foreground));
}

.geo-map .leaflet-control-attribution a {
  color: inherit;
}

.geo-map .leaflet-bar a {
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  border-color: hsl(var(--border));
}

.geo-dot-icon {
  background: none;
  border: none;
}

.geo-dot {
  position: relative;
  display: block;
  width: var(--size);
  height: var(--size);
}

.geo-dot__core {
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  background: var(--color);
  opacity: 0.85;
  box-shadow: 0 0 0 1.5px hsl(var(--background)), 0 0 12px var(--color);
}

.geo-dot--area .geo-dot__core {
  opacity: 0.45;
  box-shadow: 0 0 0 1.5px var(--color);
}

.geo-dot__ring {
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  border: 2px solid var(--color);
  opacity: 0;
}

.geo-dot--pulse .geo-dot__ring {
  animation: geo-pulse 2.4s ease-out infinite;
  animation-delay: var(--delay);
}

.geo-dot--pulse .geo-dot__ring--late {
  animation-delay: calc(var(--delay) + 1.2s);
}

@keyframes geo-pulse {
  0% {
    transform: scale(1);
    opacity: 0.8;
  }
  100% {
    transform: scale(2.8);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .geo-dot--pulse .geo-dot__ring {
    animation: none;
  }
}

.leaflet-tooltip.geo-tooltip {
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--popover));
  color: hsl(var(--popover-foreground));
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.18);
}

.leaflet-tooltip.geo-tooltip::before {
  display: none;
}

.geo-tip__title {
  font-size: 13px;
  font-weight: 600;
}

.geo-tip__sub {
  font-size: 11px;
  color: hsl(var(--muted-foreground));
}

.geo-tip__spend {
  margin-top: 4px;
  font-size: 13px;
  font-weight: 600;
}

.geo-tip__spend span {
  margin-left: 4px;
  font-size: 11px;
  font-weight: 400;
  color: hsl(var(--muted-foreground));
}

.geo-tip__row {
  display: flex;
  gap: 8px;
  margin-top: 2px;
  font-size: 11px;
  color: hsl(var(--muted-foreground));
}
</style>
