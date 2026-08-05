import { supabase } from './supabase'
import type { ClientAdAccountRow, Level, SyncResult } from './types'

const TOKEN_URL = 'https://oauth2.googleapis.com/token'
const ADS_API_URL = 'https://googleads.googleapis.com/v23/customers'
const CONFLICT_TARGET = 'client_ad_account_id,level,date,campaign_id,adset_id,ad_id'

function buildQuery(level: Level, startDate: string, endDate: string): string {
  const fields =
    level === 'campaign'
      ? 'campaign.id, campaign.name'
      : level === 'adset'
        ? 'campaign.id, campaign.name, ad_group.id, ad_group.name'
        : 'campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group_ad.ad.id, ad_group_ad.ad.name'

  const resource = level === 'campaign' ? 'campaign' : level === 'adset' ? 'ad_group' : 'ad_group_ad'

  return (
    `SELECT ${fields}, metrics.cost_micros, metrics.impressions, metrics.clicks, segments.date ` +
    `FROM ${resource} ` +
    `WHERE segments.date BETWEEN '${startDate}' AND '${endDate}' ` +
    `AND campaign.status != 'REMOVED' ` +
    `ORDER BY segments.date ASC`
  )
}

export async function syncGoogleAds(
  account: ClientAdAccountRow,
  startDate: string,
  endDate: string,
  level: Level = 'campaign',
): Promise<SyncResult> {
  const result: SyncResult = { synced: 0, errors: [] }

  const clientId = process.env.GOOGLE_ADS_CLIENT_ID
  const clientSecret = process.env.GOOGLE_ADS_CLIENT_SECRET
  const developerToken = process.env.GOOGLE_ADS_DEVELOPER_TOKEN
  const mccId = process.env.GOOGLE_ADS_MCC_ID
  const refreshToken = account.api_key_or_refresh_token

  if (!clientId || !clientSecret || !developerToken) {
    result.errors.push('Google Ads API credentials not configured in env')
    return result
  }
  if (!refreshToken) {
    result.errors.push('No refresh token found for this account.')
    return result
  }

  const tokenRes = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      client_id: clientId,
      client_secret: clientSecret,
      refresh_token: refreshToken,
      grant_type: 'refresh_token',
    }),
  })
  const tokenJson = (await tokenRes.json()) as { access_token?: string; error_description?: string }
  if (!tokenRes.ok || !tokenJson.access_token) {
    result.errors.push(tokenJson.error_description || 'Failed to obtain access token')
    return result
  }

  const accessToken = tokenJson.access_token
  const customerId = account.account_id.replace(/-/g, '')

  try {
    const headers: Record<string, string> = {
      Authorization: `Bearer ${accessToken}`,
      'developer-token': developerToken,
      'Content-Type': 'application/json',
    }
    if (mccId) headers['login-customer-id'] = mccId.replace(/-/g, '')

    const res = await fetch(`${ADS_API_URL}/${customerId}/googleAds:searchStream`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ query: buildQuery(level, startDate, endDate) }),
    })

    const bodyText = await res.text()
    if (!res.ok) {
      let error = bodyText.slice(0, 300)
      try {
        const parsed = JSON.parse(bodyText)
        error = parsed?.error?.message || parsed?.[0]?.error?.message || error
      } catch {
        // keep the truncated raw body as the error
      }
      result.errors.push(error)
      return result
    }

    type GoogleAdsRow = {
      segments?: { date?: string }
      campaign?: { id?: string | number; name?: string }
      adGroup?: { id?: string | number; name?: string }
      adGroupAd?: { ad?: { id?: string | number; name?: string } }
      metrics?: { costMicros?: string | number; impressions?: string | number; clicks?: string | number }
    }
    const batches = JSON.parse(bodyText) as Array<{ results?: GoogleAdsRow[] }>
    const rows: Record<string, unknown>[] = []

    for (const batch of batches) {
      for (const row of batch.results ?? []) {
        const date = row.segments?.date
        const campaignId = row.campaign?.id
        if (!date || campaignId === undefined) continue

        const costMicros = Number(row.metrics?.costMicros ?? 0)

        const record: Record<string, unknown> = {
          client_ad_account_id: account.id,
          level,
          date,
          campaign_id: String(campaignId),
          campaign_name: row.campaign?.name ?? 'Unknown',
          adset_id: '',
          ad_id: '',
          spend: costMicros / 1_000_000,
          impressions: Number(row.metrics?.impressions ?? 0),
          clicks: Number(row.metrics?.clicks ?? 0),
        }

        if (level === 'adset' || level === 'ad') {
          record.adset_id = String(row.adGroup?.id ?? '')
          record.adset_name = row.adGroup?.name ?? null
        }
        if (level === 'ad') {
          record.ad_id = String(row.adGroupAd?.ad?.id ?? '')
          record.ad_name = row.adGroupAd?.ad?.name ?? null
        }

        rows.push(record)
      }
    }

    if (rows.length > 0) {
      const { error } = await supabase.from('daily_ad_metrics').upsert(rows, { onConflict: CONFLICT_TARGET })
      if (error) throw new Error(error.message)
    }
    result.synced = rows.length

    await supabase.from('client_ad_accounts').update({ last_synced_at: new Date().toISOString() }).eq('id', account.id)
  } catch (e) {
    result.errors.push(e instanceof Error ? e.message : 'Unknown error')
  }

  return result
}
