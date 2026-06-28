<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUploadRequest;

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
    // バリデーションはBulkUploadRequestに委譲してControllerを薄く保つ
    // バリデーション済みの送信者情報・トーンのみをflashして想定外キーの混入を防ぐ
    public function upload(BulkUploadRequest $request)
    {
        $input = $request->safe()->only(['sender_name', 'sender_company', 'tone']);

        return redirect()->route('bulk')->withInput($input);
    }
}
