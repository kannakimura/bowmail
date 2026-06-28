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
    // withInput()で入力値をflashに保持してから戻すことでold()が機能しUXを保つ
    // ファイル自体はwithInput()でフラッシュされないため誤ったファイル保持は起きない
    public function upload()
    {
        return redirect()->route('bulk')->withInput();
    }
}
