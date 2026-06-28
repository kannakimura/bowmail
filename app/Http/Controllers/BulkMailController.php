<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// 一括メール生成機能のコントローラー
// Excel アップロード → 一括生成 → Excel ダウンロードの流れを担当する
class BulkMailController extends Controller
{
    // アップロードフォーム画面を表示する
    public function index()
    {
        return view('bulk');
    }

    // Excelアップロードを受け取る（Phase 1-3以降でパース処理を実装予定）
    // 暫定でもMIMEとサイズの最低限チェックを行い不正ファイルをサーバに蓄積させない
    // withInput()で入力値をflashに保持してから戻すことでold()が機能しUXを保つ
    public function upload(Request $request)
    {
        $request->validate([
            // xlsxのMIMEタイプを許可・最大5MBに制限する
            'file' => ['nullable', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        return redirect()->route('bulk')->withInput();
    }
}
