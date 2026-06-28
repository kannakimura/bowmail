<?php

namespace Tests\Unit;

use App\Services\BulkImportService;
use Tests\TestCase;

// BulkImportServiceのユニットテスト
// Excelファイルをパースして期待するデータ配列を返すことを検証する
class BulkImportServiceTest extends TestCase
{
    private BulkImportService $service;
    private string $validFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service   = new BulkImportService();
        $this->validFile = base_path('tests/fixtures/leads_valid.xlsx');
    }

    // 有効なExcelファイルをパースするとCollectionが返ること
    public function test_有効なExcelをパースするとCollectionが返ること(): void
    {
        $result = $this->service->parse($this->validFile);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    // 有効なExcelファイルをパースするとデータ行数が正しいこと
    public function test_有効なExcelをパースするとデータ行数が正しいこと(): void
    {
        $result = $this->service->parse($this->validFile);

        $this->assertCount(2, $result);
    }

    // パース結果の各行がconfig定義の英語キーを持つこと
    public function test_パース結果の各行が英語キーを持つこと(): void
    {
        $result  = $this->service->parse($this->validFile);
        $columns = config('bulk_import.columns', []);

        foreach ($result as $row) {
            foreach (array_keys($columns) as $key) {
                $this->assertArrayHasKey($key, $row->toArray(), "{$key}キーがありません");
            }
        }
    }

    // パース結果の1行目の値が正しいこと
    public function test_パース結果の1行目の値が正しいこと(): void
    {
        $result   = $this->service->parse($this->validFile);
        $firstRow = $result->first();

        $this->assertSame('テスト株式会社', $firstRow['company_name']);
        $this->assertSame('test@example.com', $firstRow['email']);
        $this->assertSame('料金ページ', $firstRow['visited_page']);
        $this->assertSame('比較検討中', $firstRow['phase']);
    }

    // パース結果の2行目の値が正しいこと
    public function test_パース結果の2行目の値が正しいこと(): void
    {
        $result      = $this->service->parse($this->validFile);
        $secondRow = $result->get(1);

        $this->assertSame('サンプル合同会社', $secondRow['company_name']);
        $this->assertSame('sample@example.com', $secondRow['email']);
        $this->assertSame('導入事例ページ', $secondRow['visited_page']);
        $this->assertSame('導入検討中', $secondRow['phase']);
    }
}
