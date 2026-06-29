<?php

namespace Tests\Unit;

use App\Exceptions\EmptyRowsException;
use App\Exceptions\InvalidColumnException;
use App\Exceptions\TooManyRowsException;
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

    // 拡張子なしの一時ファイルでもパースできること
    // Laravelのアップロード一時ファイルは拡張子なしのため自動検出が失敗するが
    // readerTypeにXLSXを明示することで正常にパースできることを回帰検知する
    public function test_拡張子なし一時ファイルでもパースできること(): void
    {
        // フィクスチャを拡張子なし一時ファイルにコピーしてアップロード時と同じ状況を再現する
        $tmpPath = tempnam(sys_get_temp_dir(), 'bowmail_test_');
        // 環境依存の失敗をテスト本体の失敗と混同しないよう事前条件をアサートする
        $this->assertNotFalse($tmpPath, '一時ファイルの作成に失敗しました');
        $this->assertTrue(copy($this->validFile, $tmpPath), '一時ファイルへのコピーに失敗しました');

        try {
            $result = $this->service->parse($tmpPath);
            $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
            $this->assertNotEmpty($result);
        } finally {
            // tempnamがfalseの場合はunlinkでTypeErrorになるため文字列の場合のみ削除する
            if (is_string($tmpPath)) {
                @unlink($tmpPath);
            }
        }
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

    // データ行が0件のExcelをパースするとEmptyRowsExceptionが投げられること
    public function test_データ行0件のExcelをパースするとEmptyRowsExceptionが投げられること(): void
    {
        $this->expectException(EmptyRowsException::class);

        $this->service->parse(base_path('tests/fixtures/leads_empty.xlsx'));
    }

    // 上限件数を超えるExcelをパースするとTooManyRowsExceptionが投げられること
    public function test_上限件数を超えるExcelをパースするとTooManyRowsExceptionが投げられること(): void
    {
        $this->expectException(TooManyRowsException::class);

        // max_rowsをleads_valid.xlsxの行数(2)より小さい1に下げて上限超過を再現する
        // フィクスチャ行数ではなくconfigで閾値を制御することで設定変更への追従を容易にする
        config(['bulk_import.max_rows' => 1]);

        $this->service->parse($this->validFile);
    }

    // TooManyRowsExceptionから件数と上限を取得できること
    public function test_TooManyRowsExceptionから件数と上限が取得できること(): void
    {
        $limit = config('bulk_import.max_rows', 500);
        $count = $limit + 1;
        $e     = new TooManyRowsException($count, $limit);

        $this->assertSame($count, $e->getCount());
        $this->assertSame($limit, $e->getLimit());
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
