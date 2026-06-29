<?php

namespace Tests\Unit;

use App\Exports\LeadResultExport;
use Tests\TestCase;

// LeadResultExportのユニットテスト
class LeadResultExportTest extends TestCase
{
    // ヘッダー行がconfig定義の日本語ヘッダーと一致すること
    public function test_ヘッダー行がconfig定義の日本語ヘッダーと一致すること(): void
    {
        $export   = new LeadResultExport([]);
        $headings = $export->headings();

        $expected = array_values(config('bulk_export.columns', []));

        // configが空だと常にパスしてしまうため先にアサートする
        $this->assertNotEmpty($expected, 'bulk_export.columnsが空のためヘッダー検証が無効です');
        $this->assertSame($expected, $headings);
    }

    // 正常行のmap()がconfig定義キー順に値を返すこと
    public function test_正常行のmapがconfig定義キー順に値を返すこと(): void
    {
        $row = [
            'company_name' => 'テスト株式会社',
            'visited_page' => '料金ページ',
            'phase'        => '比較検討中',
            'subject'      => 'テスト件名',
            'body'         => 'テスト本文',
        ];

        $export = new LeadResultExport([$row]);
        $result = $export->map($row);

        // 期待値をconfigキー順から組み立てることで列順変更時にテストとconfig定義が同期する
        $columns  = config('bulk_export.columns', []);
        $this->assertNotEmpty($columns, 'bulk_export.columnsが空のため列順検証が無効です');
        $expected = array_map(fn ($key) => $row[$key] ?? '', array_keys($columns));

        $this->assertSame($expected, $result);
    }

    // API失敗行の件名・本文が空文字で出力されること
    public function test_API失敗行の件名と本文が空文字で出力されること(): void
    {
        $row = [
            'company_name' => 'テスト株式会社',
            'visited_page' => '料金ページ',
            'phase'        => '比較検討中',
            'error'        => 'AIサーバーに接続できませんでした。',
        ];

        $export = new LeadResultExport([$row]);
        $result = $export->map($row);

        // 期待値をconfigキー順から組み立て、subject/bodyキーは空文字となることを検証する
        $columns  = config('bulk_export.columns', []);
        $this->assertNotEmpty($columns, 'bulk_export.columnsが空のため列順検証が無効です');
        $expected = array_map(
            fn ($key) => in_array($key, ['subject', 'body'], true) ? '' : ($row[$key] ?? ''),
            array_keys($columns)
        );

        $this->assertSame($expected, $result);
    }

    // collection()が渡した行数と同じCollectionを返すこと
    public function test_collectionが渡した行数と同じCollectionを返すこと(): void
    {
        $rows = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名1', 'body' => '本文1'],
            ['company_name' => 'B社', 'visited_page' => '導入事例ページ', 'phase' => '導入検討中', 'subject' => '件名2', 'body' => '本文2'],
        ];

        $export = new LeadResultExport($rows);
        $this->assertCount(2, $export->collection());
    }
}
