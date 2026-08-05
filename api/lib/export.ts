import { supabase } from './supabase'
import type { Level } from './types'

export type ExportRow = {
  date: string
  campaign_id: string
  campaign_name: string | null
  adset_id: string
  adset_name: string | null
  ad_id: string
  ad_name: string | null
  spend: number
  impressions: number
  clicks: number
  reach: number
}

export type ExportData = {
  account: { id: number; account_name: string; platform: 'meta' | 'google' }
  level: Level
  start_date: string
  end_date: string
  summary: { total_spend: number; total_impressions: number; total_clicks: number; total_reach: number; ctr: number }
  rows: ExportRow[]
  company_name: string
}

export async function getExportData(
  accountId: number,
  startDate: string,
  endDate: string,
  level: Level,
): Promise<ExportData | null> {
  const { data: account } = await supabase
    .from('client_ad_accounts')
    .select('id, account_name, platform')
    .eq('id', accountId)
    .maybeSingle()
  if (!account) return null

  const { data: company } = await supabase.from('company_settings').select('company_name').eq('id', 1).maybeSingle()

  const rpcArgs = { p_account_id: accountId, p_level: level, p_start: startDate, p_end: endDate }
  const [summaryRes, dailyRes] = await Promise.all([
    supabase.rpc('ads_stats_summary', rpcArgs).single(),
    supabase.rpc('ads_stats_daily', rpcArgs),
  ])

  const totals = (summaryRes.data ?? {}) as {
    total_spend?: number
    total_impressions?: number
    total_clicks?: number
    total_reach?: number
  }
  const totalImpressions = Number(totals.total_impressions ?? 0)
  const totalClicks = Number(totals.total_clicks ?? 0)
  const ctr = totalImpressions > 0 ? Math.round((totalClicks / totalImpressions) * 100 * 100) / 100 : 0

  return {
    account: account as ExportData['account'],
    level,
    start_date: startDate,
    end_date: endDate,
    summary: {
      total_spend: Number(totals.total_spend ?? 0),
      total_impressions: totalImpressions,
      total_clicks: totalClicks,
      total_reach: Number(totals.total_reach ?? 0),
      ctr,
    },
    rows: (dailyRes.data ?? []) as ExportRow[],
    company_name: company?.company_name || 'Ads Analytics',
  }
}

export function sanitizeFilename(name: string): string {
  return name.replace(/[^a-zA-Z0-9\-_.]/g, '_')
}

export function levelLabel(level: Level): string {
  return level.charAt(0).toUpperCase() + level.slice(1)
}
