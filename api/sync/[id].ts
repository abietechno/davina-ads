import type { VercelRequest, VercelResponse } from '@vercel/node'
import { supabase } from '../lib/supabase'
import { requireAuth, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'
import { syncGoogleAds } from '../lib/googleAds'
import { syncMetaAds } from '../lib/metaAds'
import type { ClientAdAccountRow, Level } from '../lib/types'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['POST'])) return

  try {
    await requireAuth(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const id = Number(req.query.id)
  if (!Number.isFinite(id)) return fail(res, 404, 'Ad account not found.')

  const body = (req.body ?? {}) as { start_date?: string; end_date?: string; level?: Level }
  const errors: Record<string, string[]> = {}
  if (!body.start_date) errors.start_date = ['Start date is required.']
  if (!body.end_date) errors.end_date = ['End date is required.']
  if (body.start_date && body.end_date && body.end_date < body.start_date) {
    errors.end_date = ['End date must be on or after start date.']
  }
  if (body.level && !['campaign', 'adset', 'ad'].includes(body.level)) errors.level = ['Invalid level.']
  if (Object.keys(errors).length > 0) return fail(res, 422, 'Validation failed.', { errors })

  const level = body.level || 'campaign'

  const { data: account } = await supabase
    .from('client_ad_accounts')
    .select('id, platform, account_name, account_id, access_token, api_key_or_refresh_token')
    .eq('id', id)
    .maybeSingle()
  if (!account) return fail(res, 404, 'Ad account not found.')

  const row = account as ClientAdAccountRow
  const result =
    row.platform === 'google'
      ? await syncGoogleAds(row, body.start_date as string, body.end_date as string, level)
      : await syncMetaAds(row, body.start_date as string, body.end_date as string, level)

  if (result.errors.length > 0) {
    return fail(res, 422, 'Sync completed with errors.', { data: result })
  }

  ok(res, result, `Synced ${result.synced} records (${level} level).`)
}
