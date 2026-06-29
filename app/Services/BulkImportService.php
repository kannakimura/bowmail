<?php

namespace App\Services;

use App\Exceptions\EmptyRowsException;
use App\Exceptions\InvalidColumnException;
use App\Exceptions\TooManyRowsException;
use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
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
        $this->validateColumns($filePath);

        $import = new LeadImport();
        Excel::import($import, $filePath);
        $rows = $import->getRows();

        $this->validateRowCount($rows);

        return $rows;
    }

    // HeadingRowImportでExcelの1行目を直接読み取り必須列を検証する
    // データ行が0件でもヘッダー行は独立して取得できるため空シートの誤検知を防げる
    // 日本語ヘッダーのスラッグ変換を防ぐためformatterを一時的にnoneにして復元する
    // 不足列がある場合はInvalidColumnExceptionを投げる
    private function validateColumns(string $filePath): void
    {
        $originalFormatter = config('excel.imports.heading_row.formatter', 'slug');
        HeadingRowFormatter::default('none');

        try {
            $headingImport  = new HeadingRowImport();
            $actualHeaders  = Excel::toArray($headingImport, $filePath)[0][0] ?? [];
        } finally {
            HeadingRowFormatter::default($originalFormatter);
        }

        $requiredHeaders = array_values(config('bulk_import.columns', []));
        $missingColumns  = array_values(array_diff($requiredHeaders, $actualHeaders));

        if (!empty($missingColumns)) {
            throw new InvalidColumnException($missingColumns);
        }
    }

    // パース済み行の件数が0件または上限超過の場合に例外を投げる
    // 上限件数はconfig/bulk_import.phpのmax_rowsで管理する
    private function validateRowCount(Collection $rows): void
    {
        $count = $rows->count();

        if ($count === 0) {
            throw new EmptyRowsException();
        }

        $limit = config('bulk_import.max_rows', 500);

        if ($count > $limit) {
            throw new TooManyRowsException($count, $limit);
        }
    }
}
