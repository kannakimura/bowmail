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

    // package:discoverによるパッケージmanifest経由でグローバルエイリアス\ExcelがFacadeとして解決できること
    public function test_グローバルエイリアスExcelがファサードとして解決できること(): void
    {
        // package:discoverが自動登録したエイリアス経由でグローバルに\Excel::classが使えること
        $this->assertTrue(class_exists(\Excel::class));
        $this->assertInstanceOf(Excel::class, \Excel::getFacadeRoot());
    }

    // config/excel.phpが読み込まれトップレベルキーが存在すること
    // has()で存在を確認したうえでデフォルト付きで取得しCLAUDE.mdのconfig規約に準拠する
    public function test_excel設定ファイルが読み込まれていること(): void
    {
        $this->assertTrue(config()->has('excel'));
        $config = config('excel', []);
        $this->assertIsArray($config);
        $this->assertArrayHasKey('exports', $config);
    }

    // 一時ファイルのローカルパスが設定されていること
    // not nullだけでなくnot emptyまで確認しパスが実際に設定されていることを保証する
    public function test_excelの一時ファイルパス設定が存在すること(): void
    {
        $path = config('excel.temporary_files.local_path', '');
        $this->assertNotEmpty($path);
    }
}
