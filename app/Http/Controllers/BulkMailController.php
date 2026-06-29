<?php

namespace App\Http\Controllers;

use App\Exceptions\EmptyRowsException;
use App\Exceptions\InvalidColumnException;
use App\Exceptions\TooManyRowsException;
use App\Http\Requests\BulkGenerateRequest;
use App\Http\Requests\BulkUploadRequest;
use App\Services\BulkGenerateService;
use App\Services\BulkImportService;
use Illuminate\Support\Facades\Log;
use Throwable;

// 一括メール生成機能のコントローラー
// Excel アップロード → プレビュー → 一括生成 → Excel ダウンロードの流れを担当する
class BulkMailController extends Controller
{
    public function __construct(
        private readonly BulkImportService $bulkImportService,
        private readonly BulkGenerateService $bulkGenerateService,
    ) {}

    // アップロードフォーム画面を表示する
    public function index()
    {
        return view('bulk');
    }

    // ExcelをBulkImportService経由でパースしてフラッシュセッションでプレビュー画面へリダイレクトする
    // redirect()->with()でflash保存することでPRGパターンを正しく実現し直アクセス時に過去データが残らない
    // パース失敗時はアップロード画面へ戻してエラーを表示する
    public function upload(BulkUploadRequest $request)
    {
        try {
            $rows = $this->bulkImportService->parse(
                $request->file('file')->getPathname()
            );
        } catch (InvalidColumnException $e) {
            // 列構成不正はユーザー操作で解決できるためログは不要でエラーメッセージを返す
            return back()
                ->withInput($request->safe()->only(['sender_name', 'sender_company', 'tone']))
                ->withErrors(['file' => '必須列が見つかりません。テンプレートのExcelファイルを使用してください。']);
        } catch (EmptyRowsException) {
            // データ行0件はユーザー操作で解決できるためログは不要でエラーメッセージを返す
            return back()
                ->withInput($request->safe()->only(['sender_name', 'sender_company', 'tone']))
                ->withErrors(['file' => 'データが1件もありません。リードを入力したExcelファイルをアップロードしてください。']);
        } catch (TooManyRowsException $e) {
            // 上限超過はユーザー操作で解決できるためログは不要でエラーメッセージを返す
            $limit = $e->getLimit();
            return back()
                ->withInput($request->safe()->only(['sender_name', 'sender_company', 'tone']))
                ->withErrors(['file' => "一度にアップロードできるリードは{$limit}件までです。ファイルを分割してアップロードしてください。"]);
        } catch (Throwable $e) {
            // ファイル破損・xlsx偽装等のパースエラーはスタックトレース付きでログに記録してユーザーに安全なメッセージを返す
            Log::error('Excelパース失敗', ['exception' => $e]);

            return back()
                ->withInput($request->safe()->only(['sender_name', 'sender_company', 'tone']))
                ->withErrors(['file' => 'ファイルの読み込みに失敗しました。破損していないxlsxファイルをアップロードしてください。']);
        }

        return redirect()->route('bulk.preview')->with([
            'bulk_input' => $request->safe()->only(['sender_name', 'sender_company', 'tone']),
            'bulk_rows'  => $rows->map(fn ($row) => $row->toArray())->toArray(),
        ]);
    }

    // プレビュー確認後にセッションのリードデータでClaude APIを順次呼び出してメールを一括生成する
    // BulkGenerateRequestでセッション存在チェックを行い、セッション切れの場合はバリデーションエラーを返す
    // 生成結果をflashセッションに保存してPRGパターンで結果画面へリダイレクトする
    // 件数が多い場合はセッション容量・書き込み負荷が増大するため、将来的にはcache/DBテーブルへの保存とIDセッション管理への移行を検討すること
    public function generate(BulkGenerateRequest $request)
    {
        $rows    = session('bulk_rows', []);
        $input   = session('bulk_input', []);
        $results = $this->bulkGenerateService->generateAll($rows, $input);

        return redirect()->route('bulk.result')->with('bulk_results', $results->toArray());
    }

    // 一括生成結果をセッションから受け取り結果画面を表示する
    public function result()
    {
        $results = session('bulk_results', []);

        return view('bulk-result', compact('results'));
    }

    // セッションからパース済みデータを受け取りプレビュー画面を表示する
    // flashデータはデフォルトで次の1リクエストで消えるため、generate()まで届くようkeepで延命する
    public function preview()
    {
        $input = session('bulk_input', []);
        $rows  = session('bulk_rows', []);

        session()->keep(['bulk_input', 'bulk_rows']);

        return view('bulk-preview', compact('input', 'rows'));
    }
}
