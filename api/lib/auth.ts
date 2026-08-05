import type { VercelRequest } from '@vercel/node'
import jwt from 'jsonwebtoken'
import { supabase } from './supabase'

const JWT_SECRET = process.env.JWT_SECRET
if (!JWT_SECRET) throw new Error('JWT_SECRET must be set')

export type Role = 'admin' | 'user'
export type Status = 'pending' | 'approved'

export type AppUser = {
  id: number
  name: string
  email: string
  role: Role
  status: Status
  created_at: string
}

const USER_COLUMNS = 'id, name, email, role, status, created_at'

export class AuthError extends Error {
  status: number
  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

export function signJwt(userId: number): string {
  return jwt.sign({ sub: userId }, JWT_SECRET as string, { expiresIn: '30d' })
}

function getBearerToken(req: VercelRequest): string | null {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) return null
  return header.slice('Bearer '.length).trim()
}

/**
 * A signed JWT doesn't auto-revoke like Sanctum's DB-backed tokens did, so the
 * user's current status/existence is re-checked from the DB on every request —
 * a deleted or de-approved user's still-valid JWT is rejected immediately.
 */
export async function requireAuth(req: VercelRequest): Promise<{ user: AppUser }> {
  const token = getBearerToken(req)
  if (!token) throw new AuthError(401, 'Unauthenticated.')

  let userId: number
  try {
    const payload = jwt.verify(token, JWT_SECRET as string) as jwt.JwtPayload
    userId = Number(payload.sub)
  } catch {
    throw new AuthError(401, 'Unauthenticated.')
  }

  const { data: user, error } = await supabase
    .from('users')
    .select(USER_COLUMNS)
    .eq('id', userId)
    .single()

  if (error || !user) throw new AuthError(401, 'Unauthenticated.')
  if (user.status !== 'approved') throw new AuthError(401, 'Your account is pending approval from an admin.')

  return { user: user as AppUser }
}

export async function requireAdmin(req: VercelRequest): Promise<{ user: AppUser }> {
  const { user } = await requireAuth(req)
  if (user.role !== 'admin') throw new AuthError(403, 'Forbidden. Admin access required.')
  return { user }
}
