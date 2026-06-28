<?php

namespace Tests\Unit;

use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

// LeadImportクラスのユニットテスト
// Excelファイルの読み込みと列定義の検証を行う
class LeadImportTest extends TestCase
{
    private string $validFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validFile = base_path('tests/fixtures/leads_valid.xlsx');
    }

    // config/bulk_import.phpに期待する列キーが定義されていること
    public function test_bulk_import設定に期待する列キーが定義されていること(): void
    {
        $columns = config('bulk_import.columns', []);

        $this->assertArrayHasKey('company_name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('visited_page', $columns);
        $this->assertArrayHasKey('phase', $columns);
    }

    // config/bulk_import.phpの値が日本語ヘッダー名であること
    public function test_bulk_import設定の値が日本語ヘッダー名であること(): void
    {
        $this->assertSame('会社名', config('bulk_import.columns.company_name'));
        $this->assertSame('メールアドレス', config('bulk_import.columns.email'));
        $this->assertSame('訪問ページ', config('bulk_import.columns.visited_page'));
        $this->assertSame('フェーズ', config('bulk_import.columns.phase'));
    }

    // 有効なExcelファイルを読み込むとデータ行数が正しいこと
    public function test_有効なExcelファイルを読み込むとデータ行数が正しいこと(): void
    {
        $import = new LeadImport();
        Excel::import($import, $this->validFile);

        // ヘッダー行を除いたデータ行が2件であること
        $this->assertCount(2, $import->getRows());
    }

    // 読み込んだ行がconfig定義の英語キーにリマップされていること
    public function test_読み込んだ行が英語キーにリマップされていること(): void
    {
        $import = new LeadImport();
        Excel::import($import, $this->validFile);

        $firstRow = $import->getRows()->first();
        $columns  = config('bulk_import.columns', []);

        foreach (array_keys($columns) as $key) {
            $this->assertArrayHasKey($key, $firstRow->toArray(), "{$key}キーがありません");
        }
    }

    // 読み込んだデータの値が正しいこと
    public function test_読み込んだデータの値が正しいこと(): void
    {
        $import = new LeadImport();
        Excel::import($import, $this->validFile);

        $firstRow = $import->getRows()->first();

        $this->assertSame('テスト株式会社', $firstRow['company_name']);
        $this->assertSame('test@example.com', $firstRow['email']);
        $this->assertSame('料金ページ', $firstRow['visited_page']);
        $this->assertSame('比較検討中', $firstRow['phase']);
    }

    // getRows()がインポート前は空のコレクションを返すこと
    public function test_インポート前はgetRowsが空コレクションを返すこと(): void
    {
        $import = new LeadImport();

        $this->assertCount(0, $import->getRows());
    }
}
