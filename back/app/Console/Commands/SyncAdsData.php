<?php

namespace App\Console\Commands;

use App\Models\ClientAdAccount;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Illuminate\Console\Command;

class SyncAdsData extends Command
{
    protected $signature = 'ads:sync {--days=7 : Number of days to sync} {--level=campaign : Detail level (campaign, adset, ad)}';
    protected $description = 'Sync ad metrics from all connected Meta & Google Ads accounts';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $level = $this->option('level');
        $accounts = ClientAdAccount::all();

        if ($accounts->isEmpty()) {
            $this->warn('No ad accounts found.');
            return 0;
        }

        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $this->info("Syncing {$accounts->count()} account(s) at [{$level}] level for {$startDate} to {$endDate}...");

        $totalSynced = 0;

        foreach ($accounts as $account) {
            $this->line("  → {$account->account_name} ({$account->platform})...");

            $service = match ($account->platform) {
                'meta' => new MetaAdsService(),
                'google' => new GoogleAdsService(),
            };

            $result = $service->sync($account, $startDate, $endDate, $level);
            $totalSynced += $result['synced'];

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->error("    ✗ {$error}");
                }
            } else {
                $this->info("    ✓ {$result['synced']} records synced.");
            }
        }

        $this->newLine();
        $this->info("Done. Total: {$totalSynced} records synced.");

        return 0;
    }
}
