import type { VercelRequest, VercelResponse } from '@vercel/node'
import { requireAuth, AuthError } from './lib/auth'
import { ok, fail, methodGuard } from './lib/http'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return

  try {
    const { user } = await requireAuth(req)
    ok(res, user)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }
}
