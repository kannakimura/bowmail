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

    // config/excel.phpのformatter設定を退避して復元に使う
    private string $originalFormatter;

    // collection()またはコンストラクタ未実行時を区別するためのフラグ
    // trueになると__destructでの二重復元を防ぐ
    private bool $formatterRestored = false;

    public function __construct()
    {
        $this->rows = collect();
        // config/excel.phpで設定されたformatterを退避して後で復元できるようにする
        $this->originalFormatter = config('excel.imports.heading_row.formatter', 'slug');
        // 日本語ヘッダーがスラッグ変換で空文字になるのを防ぐためフォーマットを無効化する
        HeadingRowFormatter::default('none');
    }

    // collection()が呼ばれなかった場合（例外・スキップ等）の保険として復元する
    // 既にcollection()で復元済みの場合は後続処理が意図的に変えたformatterを上書きしないようスキップ
    public function __destruct()
    {
        if (!$this->formatterRestored) {
            HeadingRowFormatter::default($this->originalFormatter);
        }
    }

    // WithHeadingRowがヘッダー行をスキップしコレクションとして渡す
    // config定義の期待列のみを抽出して英語キーにリマップし想定外列の混入を防ぐ
    public function collection(Collection $rows): void
    {
        $columns = config('bulk_import.columns', []);

        try {
            $this->rows = $rows->map(function ($row) use ($columns) {
                $mapped = [];
                // 期待列のみ抽出することで想定外の列が英語キーに混入しないようにする
                foreach ($columns as $englishKey => $japaneseHeader) {
                    $mapped[$englishKey] = $row[$japaneseHeader] ?? null;
                }
                return collect($mapped);
            });
        } finally {
            // 例外発生時も含め必ず元のformatterに戻し後続Importへの影響を閉じる
            HeadingRowFormatter::default($this->originalFormatter);
            $this->formatterRestored = true;
        }
    }

    // パース済みの全行を英語キーのコレクションとして返す
    public function getRows(): Collection
    {
        return $this->rows;
    }
}
