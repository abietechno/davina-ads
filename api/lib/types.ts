export type Level = 'campaign' | 'adset' | 'ad'

export type SyncResult = { synced: number; errors: string[] }

export type ClientAdAccountRow = {
  id: number
  platform: 'meta' | 'google'
  account_name: string
  account_id: string
  access_token: string
  api_key_or_refresh_token: string | null
}
