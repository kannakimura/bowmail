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

    // Excelアップロードを受け取る（Phase 1-3以降で実装予定）
    // 現時点ではアップロード画面へリダイレクトして405を回避する
    public function upload()
    {
        return redirect()->route('bulk');
    }
}
