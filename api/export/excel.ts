import type { VercelRequest, VercelResponse } from '@vercel/node'
import ExcelJS from 'exceljs'
import { requireAuth, AuthError } from '../lib/auth'
import { fail, methodGuard } from '../lib/http'
import { getExportData, sanitizeFilename, levelLabel } from '../lib/export'
import type { Level } from '../lib/types'

const THIN_GREY = { style: 'thin' as const, color: { argb: 'FFD1D5DB' } }

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

  const workbook = new ExcelJS.Workbook()
  const sheet = workbook.addWorksheet('Ads Report')

  sheet.getCell('A1').value = data.company_name
  sheet.getCell('A1').font = { bold: true, size: 14 }

  const platformLabel = data.account.platform === 'meta' ? 'Meta' : 'Google'
  sheet.getCell('A2').value = `Laporan Performa Iklan — ${platformLabel} Ads`
  sheet.getCell('A2').font = { bold: true, size: 11 }

  sheet.getCell('A3').value =
    `Akun: ${data.account.account_name} | Level: ${levelLabel(level)} | Periode: ${data.start_date} s/d ${data.end_date}`
  sheet.getCell('A3').font = { size: 9, italic: true }

  sheet.getCell('A5').value = 'RINGKASAN'
  sheet.getCell('A5').font = { bold: true, size: 10 }

  sheet.getCell('A6').value = 'Total Spend'
  sheet.getCell('B6').value = data.summary.total_spend
  sheet.getCell('B6').numFmt = '#,##0'
  sheet.getCell('C6').value = 'Impressions'
  sheet.getCell('D6').value = data.summary.total_impressions
  sheet.getCell('D6').numFmt = '#,##0'
  sheet.getCell('E6').value = 'Clicks'
  sheet.getCell('F6').value = data.summary.total_clicks
  sheet.getCell('F6').numFmt = '#,##0'
  sheet.getCell('G6').value = 'CTR'
  sheet.getCell('H6').value = `${data.summary.ctr}%`

  // ── Table headings ──
  const headerRow = 8
  const headings = ['Tanggal', 'Campaign']
  if (level === 'adset' || level === 'ad') headings.push('Ad Set')
  if (level === 'ad') headings.push('Ad')
  headings.push('Spend', 'Impressions', 'Clicks', 'Reach', 'CTR (%)')

  headings.forEach((heading, i) => {
    const cell = sheet.getRow(headerRow).getCell(i + 1)
    cell.value = heading
    cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 9 }
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F2937' } }
    cell.alignment = { horizontal: 'center' }
  })

  const lastCol = headings.length

  // ── Data rows ──
  let rowNum = headerRow + 1
  for (const row of data.rows) {
    let col = 1
    const set = (value: unknown, numFmt?: string) => {
      const cell = sheet.getRow(rowNum).getCell(col)
      cell.value = value as ExcelJS.CellValue
      if (numFmt) cell.numFmt = numFmt
      col++
    }

    set(row.date)
    set(row.campaign_name || '—')
    if (level === 'adset' || level === 'ad') set(row.adset_name || '—')
    if (level === 'ad') set(row.ad_name || '—')

    const ctr = row.impressions > 0 ? Math.round((row.clicks / row.impressions) * 100 * 100) / 100 : 0

    set(row.spend, '#,##0')
    set(row.impressions, '#,##0')
    set(row.clicks, '#,##0')
    set(row.reach, '#,##0')
    set(ctr)

    if ((rowNum - headerRow) % 2 === 0) {
      for (let c = 1; c <= lastCol; c++) {
        sheet.getRow(rowNum).getCell(c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF9FAFB' } }
      }
    }
    rowNum++
  }

  const lastDataRow = rowNum - 1
  if (lastDataRow >= headerRow) {
    for (let r = headerRow; r <= lastDataRow; r++) {
      for (let c = 1; c <= lastCol; c++) {
        sheet.getRow(r).getCell(c).border = { top: THIN_GREY, bottom: THIN_GREY, left: THIN_GREY, right: THIN_GREY }
      }
    }
  }

  for (let c = 1; c <= lastCol; c++) {
    sheet.getColumn(c).width = 18
  }

  const buffer = await workbook.xlsx.writeBuffer()
  const filename = sanitizeFilename(`ads-report-${data.account.account_name}-${data.start_date}-to-${data.end_date}.xlsx`)

  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
  res.setHeader('Content-Disposition', `attachment; filename="${filename}"`)
  res.status(200).end(Buffer.from(buffer))
}
