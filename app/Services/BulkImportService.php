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
        $rows = $import->getRows();

        $this->validateColumns($rows);

        return $rows;
    }

    // パース済み行のキーがconfig定義の必須列をすべて含むか検証する
    // 不足列がある場合はInvalidColumnExceptionを投げる
    private function validateColumns(Collection $rows): void
    {
        $requiredKeys   = array_keys(config('bulk_import.columns', []));
        $firstRow       = $rows->first();
        $actualKeys     = $firstRow ? $firstRow->keys()->all() : [];
        $missingColumns = array_values(array_diff($requiredKeys, $actualKeys));

        if (!empty($missingColumns)) {
            throw new InvalidColumnException($missingColumns);
        }
    }
}
