<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Illuminate\Support\Collection;

// ExcelのリードリストをコレクションとしてインポートするImportクラス
// WithHeadingRowにより1行目のヘッダーをキーとして各行を連想配列で取得する
// 列定義はconfig/bulk_import.phpで一元管理しここでは参照のみ行う
// WithEventsでimport実行中のみformatterを切り替え副作用をimport期間に限定する
class LeadImport implements ToCollection, WithHeadingRow, WithEvents
{
    private Collection $rows;

    // パース時に取得したExcelの実際のヘッダー行（日本語）を保持する
    private array $actualHeaders = [];

    // BeforeImportでconfig設定値を退避しAfterImport/ImportFailedで復元する
    private string $originalFormatter;

    public function __construct()
    {
        $this->rows              = collect();
        $this->originalFormatter = config('excel.imports.heading_row.formatter', 'slug');
    }

    // import実行ライフサイクルのイベントハンドラを登録する
    // BeforeImport〜AfterImport/ImportFailedの区間のみformatterを'none'にして副作用を閉じる
    public function registerEvents(): array
    {
        return [
            // import開始時にformatterを切り替えて日本語ヘッダーのスラッグ変換を無効化する
            BeforeImport::class => function (BeforeImport $event) {
                HeadingRowFormatter::default('none');
            },
            // import正常完了時にformatterを元の設定値に戻す
            AfterImport::class => function (AfterImport $event) {
                HeadingRowFormatter::default($this->originalFormatter);
            },
            // import例外時もformatterを元の設定値に戻して後続処理への影響を閉じる
            ImportFailed::class => function (ImportFailed $event) {
                HeadingRowFormatter::default($this->originalFormatter);
            },
        ];
    }

    // WithHeadingRowがヘッダー行をスキップしコレクションとして渡す
    // config定義の期待列のみを抽出して英語キーにリマップし想定外列の混入を防ぐ
    public function collection(Collection $rows): void
    {
        $columns = config('bulk_import.columns', []);

        // 1行目からExcelの実際のヘッダー（日本語キー）を取得して列構成バリデーションに使う
        $firstRow             = $rows->first();
        $this->actualHeaders  = $firstRow ? $firstRow->keys()->all() : [];

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

    // Excelから取得した実際のヘッダー行（日本語）を返す
    // BulkImportServiceの列構成バリデーションで使用する
    public function getActualHeaders(): array
    {
        return $this->actualHeaders;
    }
}
