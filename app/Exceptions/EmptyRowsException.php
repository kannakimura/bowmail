<?php

namespace App\Exceptions;

use RuntimeException;

// Excelのデータ行が0件の場合にBulkImportServiceが投げる例外
// BulkMailControllerでThrowableより先にcatchしてユーザー向けメッセージを出し分ける
class EmptyRowsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Excelにデータ行がありません');
    }
}
