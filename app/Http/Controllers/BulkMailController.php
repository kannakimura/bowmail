<?php

namespace App\Http\Controllers;

// 一括メール生成機能のコントローラー
// Excel アップロード → 一括生成 → Excel ダウンロードの流れを担当する
class BulkMailController extends Controller
{
    // アップロードフォーム画面を表示する
    public function index()
    {
        return view('bulk');
    }
}
