const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth'
const TOKEN_URL = 'https://oauth2.googleapis.com/token'
const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo'

function env(name: string): string {
  const value = process.env[name]
  if (!value) throw new Error(`${name} must be set`)
  return value
}

export function buildGoogleAuthUrl(): string {
  const params = new URLSearchParams({
    client_id: env('GOOGLE_LOGIN_CLIENT_ID'),
    redirect_uri: env('GOOGLE_LOGIN_REDIRECT_URI'),
    response_type: 'code',
    scope: 'openid email profile',
    access_type: 'online',
    prompt: 'select_account',
  })
  return `${AUTH_URL}?${params.toString()}`
}

export async function exchangeGoogleCode(code: string): Promise<{ access_token: string }> {
  const res = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      client_id: env('GOOGLE_LOGIN_CLIENT_ID'),
      client_secret: env('GOOGLE_LOGIN_CLIENT_SECRET'),
      code,
      grant_type: 'authorization_code',
      redirect_uri: env('GOOGLE_LOGIN_REDIRECT_URI'),
    }),
  })
  if (!res.ok) throw new Error(`Google token exchange failed: ${res.status} ${await res.text()}`)
  return res.json() as Promise<{ access_token: string }>
}

export async function fetchGoogleUser(accessToken: string): Promise<{ email: string; name: string }> {
  const res = await fetch(USERINFO_URL, {
    headers: { Authorization: `Bearer ${accessToken}` },
  })
  if (!res.ok) throw new Error(`Google userinfo failed: ${res.status} ${await res.text()}`)
  const data = (await res.json()) as { email?: string; name?: string }
  if (!data.email) throw new Error('Google account has no email')
  return { email: data.email, name: data.name || data.email }
}
