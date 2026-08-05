import type { VercelRequest, VercelResponse } from '@vercel/node'
import { requireAuth, AuthError } from './lib/auth'
import { fail, methodGuard } from './lib/http'
import { runSyncAll } from './lib/syncAll'
import type { Level } from './lib/types'

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['POST'])) return

  try {
    await requireAuth(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const body = (req.body ?? {}) as { level?: Level }
  const level = body.level && ['campaign', 'adset', 'ad'].includes(body.level) ? body.level : 'campaign'

  const { totalSynced, accountCount, errorsByAccount } = await runSyncAll(7, level)
  const hasErrors = Object.keys(errorsByAccount).length > 0

  // Matches the original endpoint's contract: 200 status even when some
  // accounts failed, with `success` reflecting whether any errors occurred.
  res.status(200).json({
    success: !hasErrors,
    message: `Synced ${totalSynced} records across ${accountCount} accounts.`,
    data: { synced: totalSynced, errors: errorsByAccount },
  })
}
