<?php

namespace App\Console\Commands;

use App\Models\ClientAdAccount;
use App\Services\Ads\AdSyncService;
use Illuminate\Console\Command;

class SyncAdsData extends Command
{
    protected $signature = 'ads:sync {--days=7 : Number of days to sync} {--level=campaign : Detail level (campaign, adset, ad, all)} {--account= : Only sync this client_ad_account id}';
    protected $description = 'Sync ad metrics from all active Meta & Google Ads accounts';

    private const LEVELS = ['campaign', 'adset', 'ad'];

    public function handle(AdSyncService $syncs): int
    {
        $days = (int) $this->option('days');
        $level = $this->option('level');

        if ($level === 'all') {
            // Each level is its own API report; the dashboard tabs (Campaign / Ad Set / Ad) need all three
            $exitCode = 0;
            foreach (self::LEVELS as $each) {
                $exitCode = max($exitCode, $this->syncLevel($syncs, $days, $each));
            }
            return $exitCode;
        }

        if (! in_array($level, self::LEVELS, true)) {
            $this->error('Invalid --level. Use campaign, adset, ad or all.');
            return 1;
        }

        return $this->syncLevel($syncs, $days, $level);
    }

    private function syncLevel(AdSyncService $syncs, int $days, string $level): int
    {
        $accounts = ClientAdAccount::where('is_active', true)
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active ad accounts found.');
            return 0;
        }

        $this->info("Syncing {$accounts->count()} account(s) at [{$level}] level for the last {$days} day(s)...");

        $totalSynced = 0;
        $hasErrors = false;

        foreach ($accounts as $account) {
            [$from, $to] = $syncs->rangeForDays($account, $days);
            $this->line("  → {$account->account_name} ({$account->platform}) {$from} to {$to}...");

            $result = $syncs->sync($account, $from, $to, $level);
            $totalSynced += $result['synced'];

            if (! empty($result['errors'])) {
                $hasErrors = true;
                foreach ($result['errors'] as $error) {
                    $this->error("    ✗ {$error}");
                }
            } else {
                $this->info("    ✓ {$result['synced']} records synced.");
            }
        }

        $this->newLine();
        $this->info("Done. Total: {$totalSynced} records synced.");

        return $hasErrors ? 1 : 0;
    }
}
