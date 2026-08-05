import type { VercelRequest, VercelResponse } from '@vercel/node'
import { requireAuth, AuthError } from './lib/auth'
import { ok, fail, methodGuard } from './lib/http'

// Stateless JWTs can't be server-revoked without a deny-list; the client
// discarding the token (front/src/stores/auth.js) is what actually logs out.
export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['POST'])) return

  try {
    await requireAuth(req)
    ok(res, undefined, 'Logged out successfully.')
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }
}
