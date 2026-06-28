<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MailGeneratorController;
use App\Http\Controllers\BulkMailController;

Route::get('/', [MailGeneratorController::class, 'index'])->name('home');

// 1分間に5回までに制限する（Anthropic APIの無制限消費を防ぐため）
Route::post('/generate', [MailGeneratorController::class, 'generate'])
    ->name('generate')
    ->middleware('throttle:5,1');

// 一括メール生成のアップロード画面
Route::get('/bulk', [BulkMailController::class, 'index'])->name('bulk');
// Excelアップロードの受け口（Phase 1-3以降で実装予定・現状は画面へリダイレクト）
// ファイル受信・パース時のサーバー負荷を考慮して/generateと同様にスロットリングを設定する
Route::post('/bulk/upload', [BulkMailController::class, 'upload'])
    ->name('bulk.upload')
    ->middleware('throttle:5,1');

// PRGパターンのGETエンドポイント：POST成功後にリダイレクトされる先
// セッションのflashデータから生成結果を受け取って表示する
Route::get('/result', [MailGeneratorController::class, 'result'])->name('generate.result');
