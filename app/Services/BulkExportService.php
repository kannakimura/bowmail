<?php

namespace App\Services;

use App\Exports\LeadResultExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// 一括生成結果をExcelファイルとしてダウンロードレスポンスに変換するService層
class BulkExportService
{
    // 渡された生成結果配列をLeadResultExport経由で.xlsxファイルとして返す
    public function export(array $rows): BinaryFileResponse
    {
        return Excel::download(new LeadResultExport($rows), 'bowmail_results.xlsx');
    }
}
