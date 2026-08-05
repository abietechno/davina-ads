import { supabase } from './supabase'
import { syncGoogleAds } from './googleAds'
import { syncMetaAds } from './metaAds'
import type { ClientAdAccountRow, Level } from './types'

const CONCURRENCY = 4

function syncOne(account: ClientAdAccountRow, startDate: string, endDate: string, level: Level) {
  return account.platform === 'google'
    ? syncGoogleAds(account, startDate, endDate, level)
    : syncMetaAds(account, startDate, endDate, level)
}

/**
 * Syncs every connected ad account for the last `days` days. Runs with a small
 * concurrency cap (not the original sequential loop) to stay within Vercel's
 * function duration limit as the number of connected accounts grows.
 */
export async function runSyncAll(days: number, level: Level = 'campaign') {
  const endDate = new Date().toISOString().slice(0, 10)
  const startDate = new Date(Date.now() - days * 86_400_000).toISOString().slice(0, 10)

  const { data: accounts, error } = await supabase
    .from('client_ad_accounts')
    .select('id, platform, account_name, account_id, access_token, api_key_or_refresh_token')
  if (error) throw new Error(error.message)

  const rows = (accounts ?? []) as ClientAdAccountRow[]
  let totalSynced = 0
  const errorsByAccount: Record<string, string[]> = {}

  for (let i = 0; i < rows.length; i += CONCURRENCY) {
    const batch = rows.slice(i, i + CONCURRENCY)
    const results = await Promise.allSettled(batch.map((account) => syncOne(account, startDate, endDate, level)))

    results.forEach((settled, idx) => {
      const account = batch[idx]
      if (settled.status === 'fulfilled') {
        totalSynced += settled.value.synced
        if (settled.value.errors.length > 0) errorsByAccount[account.account_name] = settled.value.errors
      } else {
        errorsByAccount[account.account_name] = [
          settled.reason instanceof Error ? settled.reason.message : 'Unknown error',
        ]
      }
    })
  }

  return { totalSynced, accountCount: rows.length, errorsByAccount }
}
