import type { VercelRequest, VercelResponse } from '@vercel/node'
import bcrypt from 'bcryptjs'
import { supabase } from './lib/supabase'
import { signJwt } from './lib/auth'
import { ok, fail, methodGuard } from './lib/http'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['POST'])) return

  const { email, password } = (req.body ?? {}) as { email?: string; password?: string }
  if (!email || !password) {
    return fail(res, 422, 'The provided credentials are incorrect.', {
      errors: { email: ['Email and password are required.'] },
    })
  }

  const { data: user } = await supabase
    .from('users')
    .select('id, name, email, role, status, password, created_at')
    .ilike('email', email)
    .maybeSingle()

  const passwordMatches = user?.password ? await bcrypt.compare(password, user.password) : false
  if (!user || !passwordMatches) {
    return fail(res, 422, 'The provided credentials are incorrect.', {
      errors: { email: ['The provided credentials are incorrect.'] },
    })
  }

  if (user.status !== 'approved') {
    return fail(res, 422, 'Your account is pending approval from an admin.', {
      errors: { email: ['Your account is pending approval from an admin.'] },
    })
  }

  const token = signJwt(user.id)
  const { password: _password, ...safeUser } = user
  ok(res, { user: safeUser, token })
}
