import type { VercelRequest, VercelResponse } from '@vercel/node'
import { supabase } from '../lib/supabase'
import { requireAuth, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'
import type { Level } from '../lib/types'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return

  try {
    await requireAuth(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const q = req.query
  const accountId = Number(q.client_ad_account_id)
  const startDate = typeof q.start_date === 'string' ? q.start_date : undefined
  const endDate = typeof q.end_date === 'string' ? q.end_date : undefined
  const level: Level =
    typeof q.level === 'string' && ['campaign', 'adset', 'ad'].includes(q.level) ? (q.level as Level) : 'campaign'

  const errors: Record<string, string[]> = {}
  if (!Number.isFinite(accountId)) errors.client_ad_account_id = ['Ad account is required.']
  if (!startDate) errors.start_date = ['Start date is required.']
  if (!endDate) errors.end_date = ['End date is required.']
  if (startDate && endDate && endDate < startDate) errors.end_date = ['End date must be on or after start date.']
  if (Object.keys(errors).length > 0) return fail(res, 422, 'Validation failed.', { errors })

  const { data: account } = await supabase.from('client_ad_accounts').select('id').eq('id', accountId).maybeSingle()
  if (!account) return fail(res, 422, 'Validation failed.', { errors: { client_ad_account_id: ['Ad account not found.'] } })

  const rpcArgs = { p_account_id: accountId, p_level: level, p_start: startDate, p_end: endDate }

  const [summaryRes, dailyRes, trendRes, breakdownRes] = await Promise.all([
    supabase.rpc('ads_stats_summary', rpcArgs).single(),
    supabase.rpc('ads_stats_daily', rpcArgs),
    supabase.rpc('ads_stats_trend', rpcArgs),
    supabase.rpc('ads_stats_breakdown', rpcArgs),
  ])

  if (summaryRes.error || dailyRes.error || trendRes.error || breakdownRes.error) {
    return fail(res, 500, 'Failed to load dashboard stats.')
  }

  const totals = summaryRes.data as {
    total_spend: number
    total_impressions: number
    total_clicks: number
    total_reach: number
  }
  const totalImpressions = Number(totals?.total_impressions ?? 0)
  const totalClicks = Number(totals?.total_clicks ?? 0)
  const ctr = totalImpressions > 0 ? Math.round((totalClicks / totalImpressions) * 100 * 100) / 100 : 0

  ok(res, {
    summary: {
      total_spend: Number(totals?.total_spend ?? 0),
      total_impressions: totalImpressions,
      total_clicks: totalClicks,
      total_reach: Number(totals?.total_reach ?? 0),
      ctr,
    },
    daily: dailyRes.data,
    trend: trendRes.data,
    breakdown: breakdownRes.data,
    level,
  })
}
