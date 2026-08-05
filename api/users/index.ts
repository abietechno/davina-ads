import type { VercelRequest, VercelResponse } from '@vercel/node'
import bcrypt from 'bcryptjs'
import { supabase } from '../lib/supabase'
import { requireAdmin, AuthError } from '../lib/auth'
import { ok, fail, methodGuard } from '../lib/http'

const SAFE_COLUMNS = 'id, name, email, role, status, created_at'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (req.method === 'GET') return handleIndex(req, res)
  if (req.method === 'POST') return handleStore(req, res)
  return methodGuard(req, res, ['GET', 'POST'])
}

async function handleIndex(req: VercelRequest, res: VercelResponse) {
  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const { data, error } = await supabase
    .from('users')
    .select(SAFE_COLUMNS)
    .order('created_at', { ascending: false })

  if (error) return fail(res, 500, 'Failed to load users.')
  ok(res, data)
}

async function handleStore(req: VercelRequest, res: VercelResponse) {
  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const body = (req.body ?? {}) as { name?: string; email?: string; password?: string }
  const errors: Record<string, string[]> = {}
  if (!body.name) errors.name = ['Name is required.']
  if (!body.email) errors.email = ['Email is required.']
  if (!body.password || body.password.length < 6) errors.password = ['Password must be at least 6 characters.']
  if (Object.keys(errors).length > 0) return fail(res, 422, 'Validation failed.', { errors })

  const { data: existing } = await supabase.from('users').select('id').ilike('email', body.email as string).maybeSingle()
  if (existing) return fail(res, 422, 'Validation failed.', { errors: { email: ['The email has already been taken.'] } })

  const hashed = await bcrypt.hash(body.password as string, 10)

  // Created directly by an admin, so no approval step needed.
  const { data, error } = await supabase
    .from('users')
    .insert({ name: body.name, email: body.email, password: hashed, role: 'user', status: 'approved' })
    .select(SAFE_COLUMNS)
    .single()

  if (error || !data) return fail(res, 500, 'Failed to create user.')
  ok(res, data, 'User created successfully.', 201)
}
