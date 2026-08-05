import type { VercelRequest, VercelResponse } from '@vercel/node'
import fs from 'node:fs/promises'
import path from 'node:path'
import { supabase } from './lib/supabase'
import { requireAdmin, AuthError } from './lib/auth'
import { ok, fail, methodGuard } from './lib/http'
import { parseMultipart } from './lib/multipart'

// Vercel doesn't parse multipart/form-data automatically; formidable needs the raw stream.
export const config = { api: { bodyParser: false } }

const BUCKET = 'branding'
const ROW_ID = 1

type SettingsRow = {
  id: number
  company_name: string
  tagline: string | null
  logo_path: string | null
  login_banner_path: string | null
}

function withUrls(row: SettingsRow) {
  return {
    ...row,
    logo_url: row.logo_path ? supabase.storage.from(BUCKET).getPublicUrl(row.logo_path).data.publicUrl : null,
    login_banner_url: row.login_banner_path
      ? supabase.storage.from(BUCKET).getPublicUrl(row.login_banner_path).data.publicUrl
      : null,
  }
}

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (req.method === 'GET') return handleShow(res)
  if (req.method === 'POST') return handleUpdate(req, res)
  return methodGuard(req, res, ['GET', 'POST'])
}

async function handleShow(res: VercelResponse) {
  const { data, error } = await supabase.from('company_settings').select('*').eq('id', ROW_ID).single()
  if (error || !data) return fail(res, 500, 'Failed to load company settings.')
  ok(res, withUrls(data as SettingsRow))
}

const IMAGE_MIME: Record<string, string> = {
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/svg+xml': 'svg',
  'image/webp': 'webp',
}

async function uploadImage(field: string, filepath: string, mimetype: string | null): Promise<string> {
  const ext = (mimetype && IMAGE_MIME[mimetype]) || path.extname(filepath).replace('.', '') || 'bin'
  const objectPath = `${field}-${Date.now()}.${ext}`
  const buffer = await fs.readFile(filepath)

  const { error } = await supabase.storage
    .from(BUCKET)
    .upload(objectPath, buffer, { contentType: mimetype || undefined, upsert: false })
  if (error) throw new Error(`Failed to upload ${field}: ${error.message}`)

  return objectPath
}

async function handleUpdate(req: VercelRequest, res: VercelResponse) {
  try {
    await requireAdmin(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const { data: current, error: fetchError } = await supabase
    .from('company_settings')
    .select('*')
    .eq('id', ROW_ID)
    .single()
  if (fetchError || !current) return fail(res, 500, 'Failed to load company settings.')

  const { fields, files } = await parseMultipart(req)
  const update: Record<string, string | null> = {}

  if (fields.company_name) update.company_name = fields.company_name
  if (fields.tagline !== undefined) update.tagline = fields.tagline

  if (files.logo) {
    const objectPath = await uploadImage('logo', files.logo.filepath, files.logo.mimetype)
    if (current.logo_path) await supabase.storage.from(BUCKET).remove([current.logo_path])
    update.logo_path = objectPath
  }

  if (files.login_banner) {
    const objectPath = await uploadImage('login_banner', files.login_banner.filepath, files.login_banner.mimetype)
    if (current.login_banner_path) await supabase.storage.from(BUCKET).remove([current.login_banner_path])
    update.login_banner_path = objectPath
  }

  const { data, error } = await supabase
    .from('company_settings')
    .update(update)
    .eq('id', ROW_ID)
    .select('*')
    .single()

  if (error || !data) return fail(res, 500, 'Failed to save company settings.')
  ok(res, withUrls(data as SettingsRow), 'Company settings updated.')
}
