import { supabase } from './supabase'
import type { ClientAdAccountRow, Level, SyncResult } from './types'

const BASE_URL = 'https://graph.facebook.com/v21.0'
const CONFLICT_TARGET = 'client_ad_account_id,level,date,campaign_id,adset_id,ad_id'

function fieldsForLevel(level: Level): string {
  if (level === 'campaign') return 'campaign_id,campaign_name,spend,impressions,clicks,reach'
  if (level === 'adset') return 'campaign_id,campaign_name,adset_id,adset_name,spend,impressions,clicks,reach'
  return 'campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,spend,impressions,clicks,reach'
}

type MetaInsightRow = {
  date_start: string
  campaign_id?: string
  campaign_name?: string
  adset_id?: string
  adset_name?: string
  ad_id?: string
  ad_name?: string
  spend?: string | number
  impressions?: string | number
  clicks?: string | number
  reach?: string | number
}

function rowsToRecords(data: MetaInsightRow[], accountId: number, level: Level): Record<string, unknown>[] {
  return data.map((row) => {
    const record: Record<string, unknown> = {
      client_ad_account_id: accountId,
      level,
      date: row.date_start,
      campaign_id: String(row.campaign_id ?? ''),
      campaign_name: row.campaign_name ?? 'Unknown',
      adset_id: level === 'campaign' ? '' : String(row.adset_id ?? ''),
      ad_id: level === 'ad' ? String(row.ad_id ?? '') : '',
      spend: Number(row.spend ?? 0),
      impressions: Number(row.impressions ?? 0),
      clicks: Number(row.clicks ?? 0),
      reach: Number(row.reach ?? 0),
    }
    if (level === 'adset' || level === 'ad') record.adset_name = row.adset_name ?? null
    if (level === 'ad') record.ad_name = row.ad_name ?? null
    return record
  })
}

export async function syncMetaAds(
  account: ClientAdAccountRow,
  startDate: string,
  endDate: string,
  level: Level = 'campaign',
): Promise<SyncResult> {
  const result: SyncResult = { synced: 0, errors: [] }

  let adAccountId = account.account_id
  if (!adAccountId.startsWith('act_')) adAccountId = `act_${adAccountId}`

  try {
    const params = new URLSearchParams({
      access_token: account.access_token,
      level,
      fields: fieldsForLevel(level),
      time_range: JSON.stringify({ since: startDate, until: endDate }),
      time_increment: '1',
      limit: '500',
    })

    const firstRes = await fetch(`${BASE_URL}/${adAccountId}/insights?${params.toString()}`)
    const firstJson = (await firstRes.json()) as {
      data?: MetaInsightRow[]
      paging?: { next?: string }
      error?: { message?: string }
    }
    if (!firstRes.ok) {
      result.errors.push(firstJson.error?.message || 'Unknown Meta API error')
      return result
    }

    const allRows = rowsToRecords(firstJson.data ?? [], account.id, level)
    let nextUrl = firstJson.paging?.next ?? null

    while (nextUrl) {
      const pageRes = await fetch(nextUrl)
      if (!pageRes.ok) break
      const pageJson = (await pageRes.json()) as { data?: MetaInsightRow[]; paging?: { next?: string } }
      const pageData = pageJson.data ?? []
      if (pageData.length === 0) break
      allRows.push(...rowsToRecords(pageData, account.id, level))
      nextUrl = pageJson.paging?.next ?? null
    }

    if (allRows.length > 0) {
      const { error } = await supabase.from('daily_ad_metrics').upsert(allRows, { onConflict: CONFLICT_TARGET })
      if (error) throw new Error(error.message)
    }
    result.synced = allRows.length

    await supabase.from('client_ad_accounts').update({ last_synced_at: new Date().toISOString() }).eq('id', account.id)
  } catch (e) {
    result.errors.push(e instanceof Error ? e.message : 'Unknown error')
  }

  return result
}
