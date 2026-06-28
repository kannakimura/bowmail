<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

// ExcelのリードリストをコレクションとしてインポートするImportクラス
// WithHeadingRowにより1行目のヘッダーをキーとして各行を連想配列で取得する
class LeadImport implements ToCollection, WithHeadingRow
{
    // 期待する列のヘッダー名（Excelの1行目と一致する必要がある）
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
    }

    // WithHeadingRowが自動でヘッダー行をスキップしコレクションとして渡す
    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    // パース済みの全行を返す
    public function getRows(): Collection
    {
        return $this->rows;
    }
}
