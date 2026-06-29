<?php

// 一括エクスポートのExcel列定義を一元管理する
// キー：生成結果配列のキー / 値：Excelの1行目に出力する日本語ヘッダー名
// インポート列（bulk_import.columns）と生成結果列（subject/body）を結合した出力仕様を定義する
return [
    'columns' => [
        'company_name' => '会社名',
        'visited_page' => '訪問ページ',
        'phase'        => 'フェーズ',
        'subject'      => '件名',
        'body'         => '本文',
    ],
];
