import type { VercelRequest, VercelResponse } from '@vercel/node'

type Envelope = { success: boolean; message?: string; data?: unknown; errors?: unknown }

export function ok(res: VercelResponse, data?: unknown, message?: string, status = 200) {
  const body: Envelope = { success: true }
  if (message) body.message = message
  if (data !== undefined) body.data = data
  res.status(status).json(body)
}

export function fail(
  res: VercelResponse,
  status: number,
  message: string,
  extra?: { errors?: unknown; data?: unknown },
) {
  const body: Envelope = { success: false, message }
  if (extra?.errors) body.errors = extra.errors
  if (extra?.data !== undefined) body.data = extra.data
  res.status(status).json(body)
}

export function methodGuard(req: VercelRequest, res: VercelResponse, allowed: string[]): boolean {
  if (!req.method || !allowed.includes(req.method)) {
    res.setHeader('Allow', allowed.join(', '))
    fail(res, 405, 'Method not allowed')
    return false
  }
  return true
}
