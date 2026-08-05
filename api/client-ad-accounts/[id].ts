import type { VercelRequest, VercelResponse } from '@vercel/node'
import { supabase } from '../lib/supabase'
import { requireAdmin, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'

const SAFE_COLUMNS = 'id, platform, account_name, account_id, last_synced_at, created_at, updated_at'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['PUT', 'DELETE'])) return

  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const id = Number(req.query.id)
  if (!Number.isFinite(id)) return fail(res, 404, 'Ad account not found.')

  if (req.method === 'PUT') return handleUpdate(req, res, id)
  return handleDestroy(res, id)
}

async function handleUpdate(req: VercelRequest, res: VercelResponse, id: number) {
  const body = (req.body ?? {}) as {
    account_name?: string
    account_id?: string
    access_token?: string
    api_key_or_refresh_token?: string
  }

  const update: Record<string, string> = {}
  if (body.account_name) update.account_name = body.account_name
  if (body.account_id) update.account_id = body.account_id
  // Only overwrite tokens when a new, non-empty value is provided.
  if (body.access_token) update.access_token = body.access_token
  if (body.api_key_or_refresh_token) update.api_key_or_refresh_token = body.api_key_or_refresh_token

  const { data, error } = await supabase
    .from('client_ad_accounts')
    .update(update)
    .eq('id', id)
    .select(SAFE_COLUMNS)
    .single()

  if (error || !data) return fail(res, 404, 'Ad account not found.')
  ok(res, data, 'Ad account updated.')
}

async function handleDestroy(res: VercelResponse, id: number) {
  // daily_ad_metrics rows are removed automatically via ON DELETE CASCADE.
  const { error } = await supabase.from('client_ad_accounts').delete().eq('id', id)
  if (error) return fail(res, 500, 'Failed to delete account.')
  ok(res, undefined, 'Ad account deleted.')
}
