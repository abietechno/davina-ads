<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import api from '@/api/axios'
import { toast } from 'vue-sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  CalendarDays,
  DollarSign,
  Eye,
  MousePointerClick,
  TrendingUp,
  Loader2,
  Plus,
  RefreshCw,
} from 'lucide-vue-next'
import SpendTrendChart from '@/components/charts/SpendTrendChart.vue'
import ImpressionsChart from '@/components/charts/ImpressionsChart.vue'
import ClicksChart from '@/components/charts/ClicksChart.vue'
import BreakdownDonutChart from '@/components/charts/BreakdownDonutChart.vue'
import PlatformLogo from '@/components/PlatformLogo.vue'

// ── State ────────────────────────────────────
const adAccounts = ref([])
const selectedAccount = ref('')
const selectedLevel = ref('campaign')
const startDate = ref(getDefaultStartDate())
const endDate = ref(getDefaultEndDate())
const syncing = ref(false)
const loadingAccounts = ref(true)
const loadingStats = ref(false)
const sheetOpen = ref(false)

const levelOptions = [
  { value: 'campaign', label: 'Campaign' },
  { value: 'adset', label: 'Ad Set' },
  { value: 'ad', label: 'Ad' },
]

const stats = ref({
  total_spend: 0,
  total_impressions: 0,
  total_clicks: 0,
  ctr: 0,
})
const dailyMetrics = ref([])
const trendData = ref([])
const breakdownData = ref([])

// ── Add Account form ─────────────────────────
const accountForm = reactive({
  platform: '',
  account_name: '',
  account_id: '',
  access_token: '',
})
const submitting = ref(false)

function getDefaultStartDate() {
  const d = new Date()
  d.setDate(1)
  return d.toISOString().split('T')[0]
}

function getDefaultEndDate() {
  return new Date().toISOString().split('T')[0]
}

// ── API calls ────────────────────────────────
async function fetchAccounts() {
  loadingAccounts.value = true
  try {
    const { data } = await api.get('/client-ad-accounts')
    adAccounts.value = data.data
    if (data.data.length > 0 && !selectedAccount.value) {
      selectedAccount.value = String(data.data[0].id)
    }
  } catch (err) {
    toast.error('Failed to load accounts', {
      description: err.response?.data?.message || err.message,
    })
  } finally {
    loadingAccounts.value = false
  }
}

async function fetchStats() {
  if (!selectedAccount.value || !startDate.value || !endDate.value) return
  loadingStats.value = true
  try {
    const { data } = await api.get('/ads/stats', {
      params: {
        client_ad_account_id: selectedAccount.value,
        start_date: startDate.value,
        end_date: endDate.value,
        level: selectedLevel.value,
      },
    })
    stats.value = data.data.summary
    dailyMetrics.value = data.data.daily
    trendData.value = data.data.trend || []
    breakdownData.value = data.data.breakdown || []
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data.errors
      toast.error('Invalid request', { description: Object.values(errors).flat()[0] })
    } else {
      toast.error('Failed to load analytics', {
        description: err.response?.data?.message || err.message,
      })
    }
  } finally {
    loadingStats.value = false
  }
}

async function handleAddAccount() {
  const f = accountForm
  if (!f.platform || !f.account_name || !f.account_id || !f.access_token) {
    toast.error('Please fill in all fields')
    return
  }
  submitting.value = true
  try {
    const { data } = await api.post('/client-ad-accounts', {
      platform: f.platform,
      account_name: f.account_name,
      account_id: f.account_id,
      access_token: f.access_token,
    })
    adAccounts.value.push(data.data)
    toast.success('Account connected', {
      description: `"${f.account_name}" has been added.`,
    })
    // Auto-select new account
    selectedAccount.value = String(data.data.id)
    // Reset form
    accountForm.platform = ''
    accountForm.account_name = ''
    accountForm.account_id = ''
    accountForm.access_token = ''
    sheetOpen.value = false
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data.errors
      toast.error('Validation error', { description: Object.values(errors).flat()[0] })
    } else {
      toast.error('Failed to save account', {
        description: err.response?.data?.message || err.message,
      })
    }
  } finally {
    submitting.value = false
  }
}

async function handleSync() {
  if (!selectedAccount.value) return
  syncing.value = true
  try {
    const { data } = await api.post(`/sync/${selectedAccount.value}`, {
      start_date: startDate.value,
      end_date: endDate.value,
      level: selectedLevel.value,
    })
    toast.success(data.message)
    // Refetch stats after sync
    await fetchStats()
  } catch (err) {
    const msg = err.response?.data?.message || err.message
    const errors = err.response?.data?.data?.errors || []
    toast.error('Sync failed', { description: errors[0] || msg })
  } finally {
    syncing.value = false
  }
}

watch([selectedAccount, selectedLevel, startDate, endDate], () => fetchStats())

onMounted(async () => {
  await fetchAccounts()
})

// ── Helpers ──────────────────────────────────
function selectedAccountObj() {
  return adAccounts.value.find((a) => String(a.id) === selectedAccount.value)
}

function selectedAccountName() {
  return selectedAccountObj()?.account_name || '—'
}

function selectedPlatform() {
  return selectedAccountObj()?.platform || ''
}

function formatCurrency(v) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v)
}

function formatNumber(v) {
  return new Intl.NumberFormat('id-ID').format(v)
}

function formatDate(v) {
  if (!v) return '—'
  return v.substring(0, 10)
}

function getRowName(row) {
  const lv = selectedLevel.value
  if (lv === 'ad') return row.ad_name || row.adset_name || row.campaign_name || '—'
  if (lv === 'adset') return row.adset_name || row.campaign_name || '—'
  return row.campaign_name || '—'
}

function getRowParent(row) {
  const lv = selectedLevel.value
  if (lv === 'ad') return row.campaign_name
  if (lv === 'adset') return row.campaign_name
  return null
}

function getCtr(clicks, impressions) {
  if (!impressions) return '0.00%'
  return ((clicks / impressions) * 100).toFixed(2) + '%'
}
</script>

<template>
  <div class="relative">
    <!-- ═══════════════════════════════════════════════ -->
    <!-- STICKY HEADER — glassmorphism                   -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="sticky top-0 z-10 border-b border-border/40 bg-background/80 backdrop-blur-xl">
      <div class="px-4 py-4 sm:px-6">
        <!-- Row 1: Title + Add button -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <PlatformLogo v-if="selectedPlatform()" :platform="selectedPlatform()" size="lg" />
            <div>
              <h1 class="font-display text-xl font-bold tracking-tight sm:text-2xl">Dashboard</h1>
              <p class="mt-0.5 text-sm text-muted-foreground">
                {{ selectedAccountName() }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <!-- Sync button -->
            <Button
              size="sm"
              variant="outline"
              class="gap-1.5 rounded-xl"
              :disabled="syncing || !selectedAccount"
              @click="handleSync"
            >
              <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
              <span class="hidden sm:inline">{{ syncing ? 'Syncing...' : 'Sync' }}</span>
            </Button>

            <!-- Add Account — opens Sheet -->
            <Sheet v-model:open="sheetOpen">
            <SheetTrigger as-child>
              <Button size="sm" class="gap-1.5 rounded-xl">
                <Plus class="h-4 w-4" />
                <span class="hidden sm:inline">Add Account</span>
              </Button>
            </SheetTrigger>
            <SheetContent side="right" class="w-full sm:max-w-md">
              <SheetHeader>
                <SheetTitle>Connect Ad Account</SheetTitle>
                <SheetDescription>
                  Add your Meta Ads or Google Ads API credentials.
                </SheetDescription>
              </SheetHeader>
              <form @submit.prevent="handleAddAccount" class="mt-6 space-y-5 px-1">
                <div class="space-y-2">
                  <Label>Platform</Label>
                  <Select v-model="accountForm.platform">
                    <SelectTrigger class="rounded-xl">
                      <SelectValue placeholder="Select platform" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="meta">Meta Ads</SelectItem>
                      <SelectItem value="google">Google Ads</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Account Name</Label>
                  <Input
                    v-model="accountForm.account_name"
                    placeholder="e.g. My Client — Main"
                    class="rounded-xl"
                  />
                </div>
                <div class="space-y-2">
                  <Label>Account ID</Label>
                  <Input
                    v-model="accountForm.account_id"
                    :placeholder="accountForm.platform === 'google' ? 'e.g. 123-456-7890' : 'e.g. act_123456789'"
                    class="rounded-xl"
                  />
                </div>
                <div class="space-y-2">
                  <Label>
                    {{ accountForm.platform === 'google' ? 'API Key / Refresh Token' : 'Access Token' }}
                  </Label>
                  <Input
                    v-model="accountForm.access_token"
                    type="password"
                    placeholder="Paste your token here"
                    class="rounded-xl"
                  />
                </div>
                <SheetFooter class="pt-2">
                  <SheetClose as-child>
                    <Button type="button" variant="outline" class="rounded-xl">Cancel</Button>
                  </SheetClose>
                  <Button type="submit" :disabled="submitting" class="rounded-xl">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ submitting ? 'Saving...' : 'Save Account' }}
                  </Button>
                </SheetFooter>
              </form>
            </SheetContent>
          </Sheet>
          </div>
        </div>

        <!-- Row 2: Filters -->
        <div class="mt-4 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:flex-wrap">
          <!-- Account selector -->
          <Select v-model="selectedAccount" :disabled="loadingAccounts || adAccounts.length === 0">
            <SelectTrigger class="w-full rounded-xl sm:w-[200px]">
              <SelectValue placeholder="Select account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="acct in adAccounts"
                :key="acct.id"
                :value="String(acct.id)"
              >
                {{ acct.account_name }}
              </SelectItem>
            </SelectContent>
          </Select>

          <!-- Level switcher -->
          <div class="flex items-center rounded-xl border border-border/50 p-0.5">
            <button
              v-for="opt in levelOptions"
              :key="opt.value"
              :class="[
                'rounded-lg px-3 py-1.5 text-xs font-medium transition-all',
                selectedLevel === opt.value
                  ? 'bg-foreground text-background'
                  : 'text-muted-foreground hover:text-foreground',
              ]"
              @click="selectedLevel = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>

          <!-- Date Range -->
          <Popover>
            <PopoverTrigger as-child>
              <Button variant="outline" class="w-full justify-start gap-2 rounded-xl font-normal sm:w-auto">
                <CalendarDays class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">{{ startDate }} &mdash; {{ endDate }}</span>
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto rounded-2xl p-4" align="start">
              <div class="space-y-4">
                <p class="text-sm font-medium font-display">Date Range</p>
                <div class="grid gap-3">
                  <div class="space-y-1.5">
                    <Label class="text-xs">From</Label>
                    <Input type="date" v-model="startDate" class="rounded-xl" />
                  </div>
                  <div class="space-y-1.5">
                    <Label class="text-xs">To</Label>
                    <Input type="date" v-model="endDate" class="rounded-xl" />
                  </div>
                </div>
                <div class="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 rounded-xl"
                    @click="startDate = getDefaultStartDate(); endDate = getDefaultEndDate()"
                  >
                    This Month
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 rounded-xl"
                    @click="() => {
                      const d = new Date()
                      d.setDate(d.getDate() - 7)
                      startDate = d.toISOString().split('T')[0]
                      endDate = getDefaultEndDate()
                    }"
                  >
                    Last 7 Days
                  </Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- BODY CONTENT                                    -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="px-4 py-6 sm:px-6">
      <!-- Empty state: no accounts -->
      <div
        v-if="!loadingAccounts && adAccounts.length === 0"
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/60 py-20 text-center"
      >
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted mb-4">
          <Plus class="h-6 w-6 text-muted-foreground" />
        </div>
        <p class="font-display text-lg font-semibold">No ad accounts yet</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Connect your first Meta or Google Ads account to start.
        </p>
        <Button class="mt-5 rounded-xl" @click="sheetOpen = true">
          <Plus class="mr-2 h-4 w-4" />
          Add Account
        </Button>
      </div>

      <template v-else>
        <!-- Summary Cards -->
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-1 pt-4 px-4">
              <CardTitle class="text-xs font-medium text-muted-foreground">Spend</CardTitle>
              <DollarSign class="h-3.5 w-3.5 text-muted-foreground" />
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <Skeleton v-if="loadingStats" class="h-7 w-24" />
              <p v-else class="font-display text-xl font-bold sm:text-2xl">
                {{ formatCurrency(stats.total_spend) }}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-1 pt-4 px-4">
              <CardTitle class="text-xs font-medium text-muted-foreground">Impressions</CardTitle>
              <Eye class="h-3.5 w-3.5 text-muted-foreground" />
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <Skeleton v-if="loadingStats" class="h-7 w-24" />
              <p v-else class="font-display text-xl font-bold sm:text-2xl">
                {{ formatNumber(stats.total_impressions) }}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-1 pt-4 px-4">
              <CardTitle class="text-xs font-medium text-muted-foreground">Clicks</CardTitle>
              <MousePointerClick class="h-3.5 w-3.5 text-muted-foreground" />
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <Skeleton v-if="loadingStats" class="h-7 w-24" />
              <p v-else class="font-display text-xl font-bold sm:text-2xl">
                {{ formatNumber(stats.total_clicks) }}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-1 pt-4 px-4">
              <CardTitle class="text-xs font-medium text-muted-foreground">CTR</CardTitle>
              <TrendingUp class="h-3.5 w-3.5 text-muted-foreground" />
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <Skeleton v-if="loadingStats" class="h-7 w-16" />
              <p v-else class="font-display text-xl font-bold sm:text-2xl">
                {{ stats.ctr }}%
              </p>
            </CardContent>
          </Card>
        </div>

        <!-- Charts Row 1: Spend Trend (full width) -->
        <div v-if="trendData.length > 1" class="mt-5">
          <Card>
            <CardHeader class="px-4 pt-4 pb-2">
              <CardTitle class="text-xs font-medium text-muted-foreground">Spend Trend</CardTitle>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <SpendTrendChart :data="trendData" />
            </CardContent>
          </Card>
        </div>

        <!-- Charts Row 2: Impressions + Clicks (side by side) -->
        <div v-if="trendData.length > 1" class="mt-3 grid gap-3 grid-cols-1 lg:grid-cols-2">
          <Card>
            <CardHeader class="px-4 pt-4 pb-2">
              <CardTitle class="text-xs font-medium text-muted-foreground">Impressions</CardTitle>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <ImpressionsChart :data="trendData" />
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="px-4 pt-4 pb-2">
              <CardTitle class="text-xs font-medium text-muted-foreground">Clicks</CardTitle>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <ClicksChart :data="trendData" />
            </CardContent>
          </Card>
        </div>

        <!-- Charts Row 3: Breakdown donut -->
        <div v-if="breakdownData.length > 1" class="mt-3">
          <Card>
            <CardHeader class="px-4 pt-4 pb-2">
              <CardTitle class="text-xs font-medium text-muted-foreground">
                Spend by {{ selectedLevel === 'ad' ? 'Ad' : selectedLevel === 'adset' ? 'Ad Set' : 'Campaign' }}
              </CardTitle>
            </CardHeader>
            <CardContent class="px-4 pb-4 flex justify-center">
              <div class="w-full max-w-sm">
                <BreakdownDonutChart :data="breakdownData" />
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Metrics Table -->
        <Card class="mt-5">
          <CardHeader class="flex flex-row items-center justify-between px-4 pt-4 pb-3">
            <CardTitle class="font-display text-base font-semibold">
              {{ selectedLevel === 'ad' ? 'Ad' : selectedLevel === 'adset' ? 'Ad Set' : 'Campaign' }} Metrics
            </CardTitle>
            <Loader2 v-if="loadingStats" class="h-4 w-4 animate-spin text-muted-foreground" />
          </CardHeader>
          <CardContent class="px-0 pb-0">
            <!-- Loading -->
            <div v-if="loadingStats" class="space-y-3 px-4 pb-4">
              <Skeleton class="h-10 w-full rounded-xl" />
              <Skeleton class="h-10 w-full rounded-xl" />
              <Skeleton class="h-10 w-full rounded-xl" />
            </div>

            <!-- Empty -->
            <div
              v-else-if="dailyMetrics.length === 0"
              class="flex flex-col items-center justify-center py-16 text-center"
            >
              <p class="text-sm text-muted-foreground">
                No {{ selectedLevel }} data found. Click <strong>Sync</strong> to fetch from the API.
              </p>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow class="border-border/40 hover:bg-transparent">
                    <TableHead class="pl-4">Date</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead v-if="selectedLevel !== 'campaign'" class="hidden lg:table-cell">Campaign</TableHead>
                    <TableHead class="text-right">Spend</TableHead>
                    <TableHead class="text-right hidden sm:table-cell">Impr.</TableHead>
                    <TableHead class="text-right hidden sm:table-cell">Clicks</TableHead>
                    <TableHead class="text-right pr-4">CTR</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow
                    v-for="(row, idx) in dailyMetrics"
                    :key="idx"
                    class="border-border/40"
                  >
                    <TableCell class="pl-4 text-muted-foreground text-sm whitespace-nowrap">{{ formatDate(row.date) }}</TableCell>
                    <TableCell class="font-medium text-sm max-w-[200px] truncate" :title="getRowName(row)">
                      {{ getRowName(row) }}
                    </TableCell>
                    <TableCell v-if="selectedLevel !== 'campaign'" class="text-xs text-muted-foreground hidden lg:table-cell max-w-[150px] truncate">
                      {{ row.campaign_name }}
                    </TableCell>
                    <TableCell class="text-right text-sm whitespace-nowrap">{{ formatCurrency(row.spend) }}</TableCell>
                    <TableCell class="text-right text-sm hidden sm:table-cell">{{ formatNumber(row.impressions) }}</TableCell>
                    <TableCell class="text-right text-sm hidden sm:table-cell">{{ formatNumber(row.clicks) }}</TableCell>
                    <TableCell class="text-right pr-4">
                      <Badge variant="secondary" class="rounded-lg text-xs font-medium">
                        {{ getCtr(row.clicks, row.impressions) }}
                      </Badge>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </template>
    </div>
  </div>
</template>
