<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MailGeneratorController;
use App\Http\Controllers\BulkMailController;

// トップページは一括生成画面にリダイレクトする
Route::get('/', fn () => redirect()->route('bulk'))->name('home');

// 1件生成画面
Route::get('/mail', [MailGeneratorController::class, 'index'])->name('mail');

// 1分間に5回までに制限する（Anthropic APIの無制限消費を防ぐため）
Route::post('/generate', [MailGeneratorController::class, 'generate'])
    ->name('generate')
    ->middleware('throttle:5,1');

// 一括メール生成のアップロード画面
Route::get('/bulk', [BulkMailController::class, 'index'])->name('bulk');
// Excelアップロードを受け取りパース結果をセッションに保存してプレビューへリダイレクトする
// ファイル受信・パース時のサーバー負荷を考慮して/generateと同様にスロットリングを設定する
Route::post('/bulk/upload', [BulkMailController::class, 'upload'])
    ->name('bulk.upload')
    ->middleware('throttle:5,1');

// PRGパターンのGETエンドポイント：upload成功後にリダイレクトされるプレビュー画面
Route::get('/bulk/preview', [BulkMailController::class, 'preview'])
    ->name('bulk.preview');

// プレビュー確認後に一括生成を実行するエンドポイント
// Anthropic APIを呼ぶため/generateと同様にスロットリングを設定する
Route::post('/bulk/generate', [BulkMailController::class, 'generate'])
    ->name('bulk.generate')
    ->middleware('throttle:5,1');

// PRGパターンのGETエンドポイント：一括生成成功後にリダイレクトされる結果画面
Route::get('/bulk/result', [BulkMailController::class, 'result'])->name('bulk.result');

// 生成結果をExcelファイルとしてダウンロードするエンドポイント
Route::get('/bulk/download', [BulkMailController::class, 'download'])->name('bulk.download');

// PRGパターンのGETエンドポイント：POST成功後にリダイレクトされる先
// セッションのflashデータから生成結果を受け取って表示する
Route::get('/result', [MailGeneratorController::class, 'result'])->name('generate.result');
