<?php

namespace Tests\Unit;

use App\Exceptions\InvalidColumnException;
use App\Services\BulkImportService;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Tests\TestCase;

// BulkImportServiceのユニットテスト
// Excelファイルをパースして各行がCollectionのCollectionを返すことを検証する
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

    // LeadImportがimport中にformatterを変更するためテスト失敗時の後続テストへの副作用を防ぐ
    protected function tearDown(): void
    {
        HeadingRowFormatter::default(config('excel.imports.heading_row.formatter', 'slug'));
        parent::tearDown();
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

        // configが空だとforeachが回らず常にパスしてしまうため先にアサートする
        $this->assertNotEmpty($columns, 'bulk_import.columnsが空のためキー検証が無効です');

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

    // 必須列が欠けているExcelをパースするとInvalidColumnExceptionが投げられること
    public function test_必須列が欠けているExcelをパースするとInvalidColumnExceptionが投げられること(): void
    {
        $this->expectException(InvalidColumnException::class);

        $this->service->parse(base_path('tests/fixtures/leads_missing_column.xlsx'));
    }

    // 必須列が欠けている場合にgetMissingColumns()で不足列名が取得できること
    public function test_列構成不正の場合に不足列名が取得できること(): void
    {
        try {
            $this->service->parse(base_path('tests/fixtures/leads_missing_column.xlsx'));
            $this->fail('InvalidColumnExceptionが投げられませんでした');
        } catch (InvalidColumnException $e) {
            // leads_missing_column.xlsxにはemail列が欠けているためconfigから日本語ヘッダーを取得して検証する
            $emailHeader = config('bulk_import.columns.email');
            $this->assertContains($emailHeader, $e->getMissingColumns());
        }
    }
}
