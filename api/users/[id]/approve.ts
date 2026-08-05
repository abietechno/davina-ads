import type { VercelRequest, VercelResponse } from '@vercel/node'
import { supabase } from '../../lib/supabase'
import { requireAdmin, AuthError } from '../../lib/auth'
import { ok, fail, methodGuard } from '../../lib/http'

const SAFE_COLUMNS = 'id, name, email, role, status, created_at'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['POST'])) return

  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const id = Number(req.query.id)
  if (!Number.isFinite(id)) return fail(res, 404, 'User not found.')

  const { data, error } = await supabase
    .from('users')
    .update({ status: 'approved' })
    .eq('id', id)
    .select(SAFE_COLUMNS)
    .single()

  if (error || !data) return fail(res, 404, 'User not found.')
  ok(res, data, 'User approved.')
}
