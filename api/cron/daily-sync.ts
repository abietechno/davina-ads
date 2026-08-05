import type { VercelRequest, VercelResponse } from '@vercel/node'
import { fail, methodGuard } from '../lib/http'
import { runSyncAll } from '../lib/syncAll'

// Vercel Cron automatically attaches `Authorization: Bearer <CRON_SECRET>` when a
// project env var named exactly CRON_SECRET exists. This is the actual auth
// boundary here — never trust the `x-vercel-cron` header alone.
export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return

  const secret = process.env.CRON_SECRET
  if (!secret || req.headers.authorization !== `Bearer ${secret}`) {
    return fail(res, 401, 'Unauthorized.')
  }

  const { totalSynced, accountCount, errorsByAccount } = await runSyncAll(3, 'campaign')
  const hasErrors = Object.keys(errorsByAccount).length > 0

  res.status(200).json({
    success: !hasErrors,
    message: `Daily sync: ${totalSynced} records across ${accountCount} accounts.`,
    data: { synced: totalSynced, errors: errorsByAccount },
  })
}
