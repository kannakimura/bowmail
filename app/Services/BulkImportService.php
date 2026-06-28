<?php

namespace App\Services;

use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

// ExcelファイルをLeadImport経由でパースしデータ配列として返すService
// Controller はこのServiceを呼び出すだけでImport実装の詳細に依存しない
class BulkImportService
{
    // Excelファイルをパースして英語キーの連想配列コレクションを返す
    // 戻り値の各要素は ['company_name', 'email', 'visited_page', 'phase'] をキーに持つ
    public function parse(string $filePath): Collection
    {
        $import = new LeadImport();
        Excel::import($import, $filePath);

        return $import->getRows();
    }
}
