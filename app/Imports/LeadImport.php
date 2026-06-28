<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Illuminate\Support\Collection;

// ExcelのリードリストをコレクションとしてインポートするImportクラス
// WithHeadingRowにより1行目のヘッダーをキーとして各行を連想配列で取得する
class LeadImport implements ToCollection, WithHeadingRow
{
    // 期待する列のヘッダー名（Excelの1行目と一致する必要がある）
    // キー：PHPで使う英語キー / 値：Excelの日本語ヘッダー名
    public const COLUMNS = [
        'company_name'  => '会社名',
        'email'         => 'メールアドレス',
        'visited_page'  => '訪問ページ',
        'phase'         => 'フェーズ',
    ];

    private Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
        // 日本語ヘッダーがスラッグ変換で空文字になるのを防ぐためフォーマットを無効化する
        HeadingRowFormatter::default('none');
    }

    // WithHeadingRowがヘッダー行をスキップしコレクションとして渡す
    // 日本語ヘッダーキーをCOLUMNS定数の英語キーにリマップして格納する
    public function collection(Collection $rows): void
    {
        $flip = array_flip(self::COLUMNS);

        $this->rows = $rows->map(function ($row) use ($flip) {
            $mapped = [];
            foreach ($row as $header => $value) {
                $key           = $flip[$header] ?? $header;
                $mapped[$key]  = $value;
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
