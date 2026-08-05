import type { VercelRequest, VercelResponse } from '@vercel/node'
import bcrypt from 'bcryptjs'
import { supabase } from '../lib/supabase'
import { requireAdmin, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'

const SAFE_COLUMNS = 'id, name, email, role, status, created_at'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['PUT', 'DELETE'])) return

  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const id = Number(req.query.id)
  if (!Number.isFinite(id)) return fail(res, 404, 'User not found.')

  if (req.method === 'PUT') return handleUpdate(req, res, id)
  return handleDestroy(res, id)
}

async function handleUpdate(req: VercelRequest, res: VercelResponse, id: number) {
  const body = (req.body ?? {}) as { name?: string; email?: string; password?: string }

  if (body.email) {
    const { data: existing } = await supabase
      .from('users')
      .select('id')
      .ilike('email', body.email)
      .neq('id', id)
      .maybeSingle()
    if (existing) return fail(res, 422, 'Validation failed.', { errors: { email: ['The email has already been taken.'] } })
  }

  const update: Record<string, string> = {}
  if (body.name) update.name = body.name
  if (body.email) update.email = body.email
  if (body.password) update.password = await bcrypt.hash(body.password, 10)

  const { data, error } = await supabase.from('users').update(update).eq('id', id).select(SAFE_COLUMNS).single()
  if (error || !data) return fail(res, 404, 'User not found.')
  ok(res, data, 'User updated.')
}

async function handleDestroy(res: VercelResponse, id: number) {
  const { count } = await supabase.from('users').select('id', { count: 'exact', head: true })
  if ((count ?? 0) <= 1) return fail(res, 422, 'Cannot delete the last user.')

  const { error } = await supabase.from('users').delete().eq('id', id)
  if (error) return fail(res, 500, 'Failed to delete user.')
  ok(res, undefined, 'User deleted.')
}
