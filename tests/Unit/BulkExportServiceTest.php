<?php

namespace Tests\Unit;

use App\Exports\LeadResultExport;
use App\Services\BulkExportService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

// BulkExportServiceのユニットテスト
class BulkExportServiceTest extends TestCase
{
    // export()がResponseを返し、渡したrowsがLeadResultExportに正しく伝播すること
    public function test_exportがxlsxダウンロードレスポンスを返しrowsが伝播すること(): void
    {
        Excel::fake();

        $rows = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名1', 'body' => '本文1'],
        ];

        $response = (new BulkExportService())->export($rows);

        // 返り値がBinaryFileResponseであること（サービスの戻り値型宣言と一致することを検証する）
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        // LeadResultExportに渡した行データがcollection()を通じて正しく伝播すること
        Excel::assertDownloaded('bowmail_results.xlsx', function (LeadResultExport $export) use ($rows) {
            return $export->collection()->toArray() === $rows;
        });
    }
}
