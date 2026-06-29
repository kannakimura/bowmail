<?php

namespace Tests\Unit;

use App\Exports\LeadResultExport;
use App\Services\BulkExportService;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

// BulkExportServiceのユニットテスト
class BulkExportServiceTest extends TestCase
{
    // export()がLeadResultExportを使って.xlsxダウンロードレスポンスを返すこと
    public function test_exportがxlsxダウンロードレスポンスを返すこと(): void
    {
        Excel::fake();

        $rows = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名1', 'body' => '本文1'],
        ];

        $service = new BulkExportService();
        $service->export($rows);

        // LeadResultExportを使ってbowmail_results.xlsxのダウンロードが実行されたことを検証する
        Excel::assertDownloaded('bowmail_results.xlsx', fn (LeadResultExport $export) => true);
    }
}
