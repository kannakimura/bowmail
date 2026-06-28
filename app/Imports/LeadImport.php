<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Illuminate\Support\Collection;

// ExcelのリードリストをコレクションとしてインポートするImportクラス
// WithHeadingRowにより1行目のヘッダーをキーとして各行を連想配列で取得する
// 列定義はconfig/bulk_import.phpで一元管理しここでは参照のみ行う
class LeadImport implements ToCollection, WithHeadingRow
{
    private Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
        // 日本語ヘッダーがスラッグ変換で空文字になるのを防ぐためフォーマットを無効化する
        HeadingRowFormatter::default('none');
    }

    // WithHeadingRowがヘッダー行をスキップしコレクションとして渡す
    // 日本語ヘッダーキーをconfig/bulk_import.phpの英語キーにリマップして格納する
    public function collection(Collection $rows): void
    {
        $columns = config('bulk_import.columns', []);
        $flip    = array_flip($columns);

        $this->rows = $rows->map(function ($row) use ($flip) {
            $mapped = [];
            foreach ($row as $header => $value) {
                $key          = $flip[$header] ?? $header;
                $mapped[$key] = $value;
            }
            return collect($mapped);
        });
    }

    // パース済みの全行を英語キーのコレクションとして返す
    public function getRows(): Collection
    {
        return $this->rows;
    }
}
