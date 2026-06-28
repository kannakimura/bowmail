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

    // インスタンス破棄時にグローバル設定をデフォルトに戻して他のImportへの影響を閉じる
    public function __destruct()
    {
        HeadingRowFormatter::default('slug');
    }

    // WithHeadingRowがヘッダー行をスキップしコレクションとして渡す
    // config定義の期待列のみを抽出して英語キーにリマップし想定外列の混入を防ぐ
    public function collection(Collection $rows): void
    {
        $columns = config('bulk_import.columns', []);

        $this->rows = $rows->map(function ($row) use ($columns) {
            $mapped = [];
            // 期待列のみ抽出することで想定外の列が英語キーに混入しないようにする
            foreach ($columns as $englishKey => $japaneseHeader) {
                $mapped[$englishKey] = $row[$japaneseHeader] ?? null;
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
