<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

// 一括生成結果をExcelファイルとしてエクスポートするExportクラス
// 列定義はconfig/bulk_export.phpで一元管理しここでは参照のみ行う
// WithHeadingsで1行目にヘッダーを出力し、WithMappingでconfig定義順に列を並べる
class LeadResultExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    // config定義の日本語ヘッダー名を1行目に出力する
    public function headings(): array
    {
        return array_values(config('bulk_export.columns', []));
    }

    // config定義のキー順に各行の値を並べて返す
    // 生成失敗行（errorキーあり）は件名・本文を空文字で出力する
    public function map($row): array
    {
        $columns = config('bulk_export.columns', []);
        $result  = [];

        foreach (array_keys($columns) as $key) {
            // errorキーが存在する行（null値含む）は件名・本文を空文字にしてリード情報は残す
            if (array_key_exists('error', $row) && in_array($key, ['subject', 'body'], true)) {
                $result[] = '';
            } else {
                $result[] = $row[$key] ?? '';
            }
        }

        return $result;
    }
}
