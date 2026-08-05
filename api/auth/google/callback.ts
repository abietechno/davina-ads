import type { VercelRequest, VercelResponse } from '@vercel/node'
import crypto from 'node:crypto'
import bcrypt from 'bcryptjs'
import { supabase } from '../../lib/supabase'
import { exchangeGoogleCode, fetchGoogleUser } from '../../lib/google'
import { signJwt } from '../../lib/auth'
import { methodGuard } from '../../lib/http'

function frontendUrl(): string {
  return process.env.FRONTEND_URL || 'https://ads.pulsepowerhub.id'
}

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return

  const code = typeof req.query.code === 'string' ? req.query.code : null
  if (!code) {
    return res.redirect(302, `${frontendUrl()}/login?error=google_auth_failed`)
  }

  let email: string
  let name: string
  try {
    const { access_token } = await exchangeGoogleCode(code)
    const googleUser = await fetchGoogleUser(access_token)
    email = googleUser.email
    name = googleUser.name
  } catch {
    return res.redirect(302, `${frontendUrl()}/login?error=google_auth_failed`)
  }

  let { data: user } = await supabase
    .from('users')
    .select('id, name, email, role, status')
    .ilike('email', email)
    .maybeSingle()

  if (!user) {
    const adminEmail = process.env.ADMIN_EMAIL || 'abietechno.id@gmail.com'
    const isDesignatedAdmin = adminEmail.toLowerCase() === email.toLowerCase()
    const randomPassword = await bcrypt.hash(crypto.randomBytes(24).toString('hex'), 10)

    const { data: created, error } = await supabase
      .from('users')
      .insert({
        name,
        email,
        password: randomPassword,
        role: isDesignatedAdmin ? 'admin' : 'user',
        status: isDesignatedAdmin ? 'approved' : 'pending',
      })
      .select('id, name, email, role, status')
      .single()

    if (error || !created) {
      return res.redirect(302, `${frontendUrl()}/login?error=google_auth_failed`)
    }
    user = created
  }

  if (user.status !== 'approved') {
    return res.redirect(302, `${frontendUrl()}/login?error=pending_approval`)
  }

  const token = signJwt(user.id)
  res.redirect(302, `${frontendUrl()}/auth/callback?token=${token}&name=${encodeURIComponent(user.name)}`)
}
