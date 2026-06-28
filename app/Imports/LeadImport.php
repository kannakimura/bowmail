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

    // config/excel.phpのformatter設定を保持して__destructで元の値に戻す
    private string $originalFormatter;

    public function __construct()
    {
        $this->rows = collect();
        // config/excel.phpで設定されたformatterを退避して後で復元できるようにする
        $this->originalFormatter = config('excel.imports.heading_row.formatter', 'slug');
        // 日本語ヘッダーがスラッグ変換で空文字になるのを防ぐためフォーマットを無効化する
        HeadingRowFormatter::default('none');
    }

    // 例外などで collection() が呼ばれなかった場合の保険としてグローバル設定を元に戻す
    public function __destruct()
    {
        HeadingRowFormatter::default($this->originalFormatter);
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

        // 通常系では即座に元のformatterに戻して同一リクエスト内の後続Importへの影響を防ぐ
        HeadingRowFormatter::default($this->originalFormatter);
    }

    // パース済みの全行を英語キーのコレクションとして返す
    public function getRows(): Collection
    {
        return $this->rows;
    }
}
