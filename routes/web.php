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

// PRGパターンのGETエンドポイント：POST成功後にリダイレクトされる先
// セッションのflashデータから生成結果を受け取って表示する
Route::get('/result', [MailGeneratorController::class, 'result'])->name('generate.result');
