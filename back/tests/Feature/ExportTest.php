<?php

namespace Tests\Feature;

use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private ClientAdAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));

        $this->account = ClientAdAccount::create([
            'platform' => 'meta',
            'account_name' => 'Davina Ads Group',
            'account_id' => '1501772011366761',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'conversion_action_type' => 'onsite_conversion.messaging_conversation_started_7d',
        ]);

        $this->metric('2026-10-21', '1', 'Campaign Oktober', ['spend' => 200000, 'impressions' => 9000, 'clicks' => 90, 'conversions' => 5]);
        $this->metric('2026-10-22', '1', 'Campaign Oktober', ['spend' => 100000, 'impressions' => 4000, 'clicks' => 40, 'conversions' => 3]);
        $this->metric('2026-10-22', '2', 'Campaign November', ['spend' => 400000, 'impressions' => 8000, 'clicks' => 20, 'conversions' => 2]);
        // A paused campaign Meta still returns with all zeros
        $this->metric('2026-10-23', '3', 'Campaign Juli', ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0]);
    }

    private function metric(string $date, string $campaignId, string $campaignName, array $values): void
    {
        DailyAdMetric::create($values + [
            'client_ad_account_id' => $this->account->id,
            'level' => 'campaign',
            'date' => $date,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
        ]);
    }

    private function query(): string
    {
        return http_build_query([
            'client_ad_account_id' => $this->account->id,
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-31',
        ]);
    }

    public function test_excel_report_includes_conversions_and_campaign_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:20:00', 'UTC'));

        $response = $this->get('/api/export/excel?' . $this->query());
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        unlink($path);

        $summary = $book->getSheetByName('Ringkasan');
        $this->assertSame('Laporan Performa Meta Ads', $summary->getCell('A2')->getValue());
        $this->assertSame('Chat WA', $summary->getCell('A5')->getValue());
        $this->assertEquals(10, $summary->getCell('B5')->getValue());
        $this->assertSame('Biaya per chat WA', $summary->getCell('A6')->getValue());
        $this->assertEquals(70000, $summary->getCell('B6')->getValue());

        // Totals per campaign, biggest spend first, paused campaign left out
        $this->assertSame('Campaign November', $summary->getCell('A15')->getValue());
        $this->assertEquals(200000, $summary->getCell('D15')->getValue());
        $this->assertSame('Campaign Oktober', $summary->getCell('A16')->getValue());
        $this->assertEquals(37500, $summary->getCell('D16')->getValue());
        $this->assertNull($summary->getCell('A17')->getValue());

        $this->assertSame('Laporan dibuat 19 Nov 2026 20:20 WITA', $summary->getCell('A18')->getValue());

        $daily = $book->getSheetByName('Harian');
        $this->assertSame('Chat WA', $daily->getCell('D1')->getValue());
        $this->assertSame(4, $daily->getHighestDataRow());
    }

    public function test_google_report_uses_generic_conversion_label_and_hides_reach(): void
    {
        $this->account->update(['platform' => 'google', 'conversion_action_type' => null]);

        $response = $this->get('/api/export/excel?' . $this->query());
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $daily = IOFactory::load($path)->getSheetByName('Harian');
        unlink($path);

        $headings = $daily->rangeToArray('A1:J1')[0];
        $this->assertContains('Konversi', $headings);
        $this->assertNotContains('Reach', $headings);
    }

    public function test_pdf_report_downloads(): void
    {
        $this->get('/api/export/pdf?' . $this->query())
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
