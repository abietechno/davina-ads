import type { VercelRequest, VercelResponse } from '@vercel/node'
import { buildGoogleAuthUrl } from '../../lib/google'
import { ok, methodGuard } from '../../lib/http'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return
  ok(res, { url: buildGoogleAuthUrl() })
}
