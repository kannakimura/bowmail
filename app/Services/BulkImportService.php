<?php

namespace App\Services;

use App\Exceptions\InvalidColumnException;
use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

// ExcelファイルをLeadImport経由でパースしCollectionとして返すService
// Controller はこのServiceを呼び出すだけでImport実装の詳細に依存しない
class BulkImportService
{
    // Excelファイルをパースして各行がCollectionの要素であるCollectionを返す
    // 各行のキーはconfig/bulk_import.phpのcolumns定義に従って決まる
    // 必須列が1つでも欠けている場合はInvalidColumnExceptionを投げる
    public function parse(string $filePath): Collection
    {
        $import = new LeadImport();
        Excel::import($import, $filePath);

        $this->validateColumns($import);

        return $import->getRows();
    }

    // ExcelのヘッダーがconfigのJapanese列名（値）をすべて含むか検証する
    // LeadImportは??null で常に全英語キーをマップするため英語キーではなく日本語ヘッダーで判定する
    // 不足列がある場合はInvalidColumnExceptionを投げる
    private function validateColumns(LeadImport $import): void
    {
        $requiredHeaders = array_values(config('bulk_import.columns', []));
        $actualHeaders   = $import->getActualHeaders();
        $missingColumns  = array_values(array_diff($requiredHeaders, $actualHeaders));

        if (!empty($missingColumns)) {
            throw new InvalidColumnException($missingColumns);
        }
    }
}
