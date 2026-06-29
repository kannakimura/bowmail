<?php

namespace App\Exceptions;

use RuntimeException;

// Excelの列構成がconfig定義と一致しない場合にBulkImportServiceが投げる例外
// BulkMailControllerでThrowableより先にcatchしてユーザー向けメッセージを出し分ける
class InvalidColumnException extends RuntimeException
{
    // 不足している列名一覧を保持してエラーメッセージの詳細化に使う
    public function __construct(private readonly array $missingColumns)
    {
        parent::__construct('Excelの列構成が不正です');
    }

    public function getMissingColumns(): array
    {
        return $this->missingColumns;
    }
}
