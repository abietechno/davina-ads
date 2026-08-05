import type { VercelRequest, VercelResponse } from '@vercel/node'
import { supabase } from '../lib/supabase'
import { requireAuth, requireAdmin, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'

// Never select access_token/api_key_or_refresh_token here — those are write-only.
const SAFE_COLUMNS = 'id, platform, account_name, account_id, last_synced_at, created_at, updated_at'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (req.method === 'GET') return handleIndex(req, res)
  if (req.method === 'POST') return handleStore(req, res)
  return methodGuard(req, res, ['GET', 'POST'])
}

async function handleIndex(req: VercelRequest, res: VercelResponse) {
  try {
    await requireAuth(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const { data, error } = await supabase.from('client_ad_accounts').select(SAFE_COLUMNS)
  if (error) return fail(res, 500, 'Failed to load accounts.')
  ok(res, data)
}

async function handleStore(req: VercelRequest, res: VercelResponse) {
  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const body = (req.body ?? {}) as {
    platform?: string
    account_name?: string
    account_id?: string
    access_token?: string
    api_key_or_refresh_token?: string | null
  }

  const errors: Record<string, string[]> = {}
  if (body.platform !== 'meta' && body.platform !== 'google') errors.platform = ['Platform must be meta or google.']
  if (!body.account_name) errors.account_name = ['Account name is required.']
  if (!body.account_id) errors.account_id = ['Account ID is required.']
  if (!body.access_token) errors.access_token = ['Access token is required.']
  if (Object.keys(errors).length > 0) return fail(res, 422, 'Validation failed.', { errors })

  const { data, error } = await supabase
    .from('client_ad_accounts')
    .insert({
      platform: body.platform,
      account_name: body.account_name,
      account_id: body.account_id,
      access_token: body.access_token,
      api_key_or_refresh_token: body.api_key_or_refresh_token || null,
    })
    .select(SAFE_COLUMNS)
    .single()

  if (error || !data) return fail(res, 500, 'Failed to save account.')
  ok(res, data, 'Ad account saved successfully.', 201)
}
