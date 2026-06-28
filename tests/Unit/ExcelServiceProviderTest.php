<?php

namespace Tests\Unit;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Tests\TestCase;

// maatwebsite/excelの導入確認テスト
// パッケージが正しく登録・解決できることを検証する
class ExcelServiceProviderTest extends TestCase
{
    // Excelクラスがサービスコンテナから解決できること
    public function test_Excelクラスがコンテナから解決できること(): void
    {
        $excel = $this->app->make(Excel::class);

        $this->assertInstanceOf(Excel::class, $excel);
    }

    // ExcelファサードがExcelクラスのインスタンスを返すこと
    public function test_ExcelファサードがExcelクラスを返すこと(): void
    {
        $this->assertInstanceOf(Excel::class, ExcelFacade::getFacadeRoot());
    }

    // config/excel.phpが読み込まれていること
    public function test_excel設定ファイルが読み込まれていること(): void
    {
        $this->assertNotNull(config('excel'));
        $this->assertIsArray(config('excel'));
    }

    // デフォルトの一時保存ディスクが設定されていること
    public function test_excelの一時ディスク設定が存在すること(): void
    {
        $this->assertNotNull(config('excel.temporary_files.local_path'));
    }
}
