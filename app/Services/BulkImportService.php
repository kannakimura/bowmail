<?php

namespace App\Services;

use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

// ExcelファイルをLeadImport経由でパースしCollectionとして返すService
// Controller はこのServiceを呼び出すだけでImport実装の詳細に依存しない
class BulkImportService
{
    // Excelファイルをパースして各行がCollectionの要素であるCollectionを返す
    // 各行のCollectionは ['company_name', 'email', 'visited_page', 'phase'] をキーに持つ
    public function parse(string $filePath): Collection
    {
        $import = new LeadImport();
        Excel::import($import, $filePath);

        return $import->getRows();
    }
}
