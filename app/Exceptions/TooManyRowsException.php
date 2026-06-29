<?php

namespace App\Exceptions;

use RuntimeException;

// Excelのデータ行が上限件数を超えた場合にBulkImportServiceが投げる例外
// BulkMailControllerでThrowableより先にcatchしてユーザー向けメッセージを出し分ける
class TooManyRowsException extends RuntimeException
{
    public function __construct(private readonly int $count, private readonly int $limit)
    {
        parent::__construct("Excelのデータ行数({$count}件)が上限({$limit}件)を超えています");
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
