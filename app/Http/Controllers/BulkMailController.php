<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUploadRequest;
use App\Services\BulkImportService;

// 一括メール生成機能のコントローラー
// Excel アップロード → プレビュー → 一括生成 → Excel ダウンロードの流れを担当する
class BulkMailController extends Controller
{
    public function __construct(private readonly BulkImportService $bulkImportService) {}

    // アップロードフォーム画面を表示する
    public function index()
    {
        return view('bulk');
    }

    // ExcelをBulkImportService経由でパースしてフラッシュセッションでプレビュー画面へリダイレクトする
    // redirect()->with()でflash保存することでPRGパターンを正しく実現し直アクセス時に過去データが残らない
    public function upload(BulkUploadRequest $request)
    {
        $rows = $this->bulkImportService->parse(
            $request->file('file')->getPathname()
        );

        return redirect()->route('bulk.preview')->with([
            'bulk_input' => $request->safe()->only(['sender_name', 'sender_company', 'tone']),
            'bulk_rows'  => $rows->map(fn ($row) => $row->toArray())->toArray(),
        ]);
    }

    // セッションからパース済みデータを受け取りプレビュー画面を表示する
    public function preview()
    {
        $input = session('bulk_input', []);
        $rows  = session('bulk_rows', []);

        return view('bulk-preview', compact('input', 'rows'));
    }
}
