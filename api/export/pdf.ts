import type { VercelRequest, VercelResponse } from '@vercel/node'
import PDFDocument from 'pdfkit'
import { requireAuth, AuthError } from '../lib/auth'
import { fail, methodGuard } from '../lib/http'
import { getExportData, sanitizeFilename, levelLabel, type ExportRow } from '../lib/export'
import type { Level } from '../lib/types'

const IDR = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 })

function columnsForLevel(level: Level) {
  const cols = [
    { key: 'date', label: 'Tanggal', width: 70 },
    { key: 'campaign_name', label: 'Campaign', width: 150 },
  ]
  if (level === 'adset' || level === 'ad') cols.push({ key: 'adset_name', label: 'Ad Set', width: 130 })
  if (level === 'ad') cols.push({ key: 'ad_name', label: 'Ad', width: 130 })
  cols.push(
    { key: 'spend', label: 'Spend', width: 90 },
    { key: 'impressions', label: 'Impr.', width: 80 },
    { key: 'clicks', label: 'Clicks', width: 70 },
    { key: 'reach', label: 'Reach', width: 70 },
    { key: 'ctr', label: 'CTR', width: 60 },
  )
  return cols
}

export default async function handler(req: VercelRequest, res: VercelResponse) {
  if (!methodGuard(req, res, ['GET'])) return

  try {
    await requireAuth(req)
  } catch (e) {
    if (e instanceof AuthError) return fail(res, e.status, e.message)
    throw e
  }

  const q = req.query
  const accountId = Number(q.client_ad_account_id)
  const startDate = typeof q.start_date === 'string' ? q.start_date : undefined
  const endDate = typeof q.end_date === 'string' ? q.end_date : undefined
  const level: Level =
    typeof q.level === 'string' && ['campaign', 'adset', 'ad'].includes(q.level) ? (q.level as Level) : 'campaign'

  if (!Number.isFinite(accountId) || !startDate || !endDate) {
    return fail(res, 422, 'Validation failed.')
  }

  const data = await getExportData(accountId, startDate, endDate, level)
  if (!data) return fail(res, 422, 'Validation failed.', { errors: { client_ad_account_id: ['Ad account not found.'] } })

  const filename = sanitizeFilename(`ads-report-${data.account.account_name}-${data.start_date}-to-${data.end_date}.pdf`)
  res.setHeader('Content-Type', 'application/pdf')
  res.setHeader('Content-Disposition', `attachment; filename="${filename}"`)

  const doc = new PDFDocument({ size: 'A4', layout: 'landscape', margin: 36 })
  doc.pipe(res)

  // ── Header ──
  doc.fontSize(16).fillColor('#1f2937').text(data.company_name, { continued: false })
  doc
    .fontSize(11)
    .fillColor('#3b82f6')
    .text(`Laporan Performa Iklan — ${data.account.platform === 'meta' ? 'Meta' : 'Google'} Ads`)
  doc
    .fontSize(9)
    .fillColor('#6b7280')
    .text(
      `Akun: ${data.account.account_name} | Level: ${levelLabel(level)} | Periode: ${data.start_date} s/d ${data.end_date}`,
    )
  doc.moveDown(1)

  // ── Summary cards ──
  const summaryY = doc.y
  const cardWidth = (doc.page.width - 72) / 4
  const cards = [
    ['Total Spend', `Rp ${IDR.format(data.summary.total_spend)}`],
    ['Impressions', IDR.format(data.summary.total_impressions)],
    ['Clicks', IDR.format(data.summary.total_clicks)],
    ['CTR', `${data.summary.ctr}%`],
  ]
  cards.forEach(([label, value], i) => {
    const x = 36 + i * cardWidth
    doc.rect(x, summaryY, cardWidth - 8, 44).stroke('#e5e7eb')
    doc.fontSize(8).fillColor('#6b7280').text(label, x + 8, summaryY + 8)
    doc.fontSize(13).fillColor('#111827').text(value, x + 8, summaryY + 20)
  })
  doc.y = summaryY + 60
  doc.moveDown(0.5)

  // ── Table ──
  const columns = columnsForLevel(level)
  const tableX = 36

  function drawHeaderRow() {
    let x = tableX
    const y = doc.y
    doc.rect(tableX, y, columns.reduce((s, c) => s + c.width, 0), 20).fill('#1f2937')
    doc.fontSize(8).fillColor('#ffffff')
    columns.forEach((c) => {
      doc.text(c.label, x + 4, y + 6, { width: c.width - 8 })
      x += c.width
    })
    doc.y = y + 20
  }

  doc.fontSize(11).fillColor('#374151').text(`Detail per ${levelLabel(level)}`)
  doc.moveDown(0.3)
  drawHeaderRow()

  const rowValue = (row: ExportRow, key: string): string => {
    if (key === 'date') return row.date
    if (key === 'campaign_name') return row.campaign_name || '—'
    if (key === 'adset_name') return row.adset_name || '—'
    if (key === 'ad_name') return row.ad_name || '—'
    if (key === 'spend') return `Rp ${IDR.format(row.spend)}`
    if (key === 'impressions') return IDR.format(row.impressions)
    if (key === 'clicks') return IDR.format(row.clicks)
    if (key === 'reach') return IDR.format(row.reach)
    if (key === 'ctr') return row.impressions > 0 ? `${((row.clicks / row.impressions) * 100).toFixed(2)}%` : '0.00%'
    return ''
  }

  if (data.rows.length === 0) {
    doc.fontSize(9).fillColor('#9ca3af').text('Tidak ada data untuk periode ini.', tableX, doc.y + 6)
  }

  data.rows.forEach((row, idx) => {
    if (doc.y > doc.page.height - 60) {
      doc.addPage()
      drawHeaderRow()
    }
    const y = doc.y
    if (idx % 2 === 1) doc.rect(tableX, y, columns.reduce((s, c) => s + c.width, 0), 16).fill('#f9fafb')
    let x = tableX
    doc.fontSize(8).fillColor('#1f2937')
    columns.forEach((c) => {
      doc.text(rowValue(row, c.key), x + 4, y + 4, { width: c.width - 8 })
      x += c.width
    })
    doc.y = y + 16
  })

  doc.end()
}
