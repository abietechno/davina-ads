import type { VercelRequest } from '@vercel/node'
import formidable from 'formidable'

export type ParsedForm = {
  fields: Record<string, string>
  files: Record<string, { filepath: string; mimetype: string | null; originalFilename: string | null }>
}

export async function parseMultipart(req: VercelRequest): Promise<ParsedForm> {
  const form = formidable({ multiples: false })
  const [rawFields, rawFiles] = await form.parse(req)

  const fields: Record<string, string> = {}
  for (const [key, value] of Object.entries(rawFields)) {
    if (Array.isArray(value) && value.length > 0) fields[key] = value[0] as string
  }

  const files: ParsedForm['files'] = {}
  for (const [key, value] of Object.entries(rawFiles)) {
    const file = Array.isArray(value) ? value[0] : value
    if (file) {
      files[key] = {
        filepath: file.filepath,
        mimetype: file.mimetype,
        originalFilename: file.originalFilename,
      }
    }
  }

  return { fields, files }
}
