<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// リードナーチャリングメール生成機能のコントローラー
class MailGeneratorController extends Controller
{
    // 入力フォーム画面を表示する
    public function index()
    {
        return view('mail-generator');
    }

    // フォームの入力値を受け取り、Claude APIでメールを生成して結果を返す
    public function generate(Request $request)
    {
        // バリデーション：必須項目とトーンの選択肢を検証する
        // company_nameは任意だがnullable|stringを指定して配列送信によるエラーを防ぐ
        // visited_page・phaseはin:でホワイトリスト検証してプロンプト注入を防ぐ
        $request->validate([
            'company_name'   => 'nullable|string|max:100',
            'visited_page'   => 'required|in:料金ページ,導入事例ページ,機能紹介ページ,資料ダウンロードページ,お問い合わせページ（未送信）,トップページ',
            'phase'          => 'required|in:認知（初回訪問）,比較検討中,導入検討中,失注後フォロー',
            'sender_name'    => 'required|string|max:100',
            'sender_company' => 'required|string|max:100',
            'tone'           => 'required|in:polite,casual',
        ]);

        // 入力値を変数に取り出す（company_nameは任意のため空文字をデフォルトに）
        $companyName   = $request->input('company_name', '');
        $visitedPage   = $request->input('visited_page');
        $phase         = $request->input('phase');
        $senderName    = $request->input('sender_name');
        $senderCompany = $request->input('sender_company');
        // トーンをプロンプト向けの日本語表現に変換する
        $tone = $request->input('tone') === 'polite' ? '丁寧（ビジネスフォーマル）' : 'カジュアル（親しみやすい）';

        // 会社名が入力されている場合とない場合でプロンプトの文言を分ける
        $companyLine = $companyName ? "相手の会社名：{$companyName}" : '相手の会社名：不明';

        // Claude APIへ送るプロンプトを組み立てる
        $prompt = <<<PROMPT
あなたはBtoBマーケティング担当者のメール文章作成を支援するAIです。
以下の情報をもとに、リードナーチャリング用のメール件名と本文を1案作成してください。

{$companyLine}
訪問したページ：{$visitedPage}
検討フェーズ：{$phase}
送信者名：{$senderName}
送信者会社名：{$senderCompany}
メールのトーン：{$tone}

出力フォーマット：
件名：（ここに件名）

本文：
（ここに本文）

注意：
- 押しつけがましくなく、相手の関心に寄り添う内容にする
- 本文は200〜300文字程度
- 署名は含めない
PROMPT;

        // Claude APIを呼び出す（タイムアウト・接続エラーはConnectionExceptionで捕捉する）
        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } catch (ConnectionException $e) {
            // タイムアウトや接続失敗の場合は500にせずフォームに戻す
            return back()->withInput()->withErrors(['api' => 'AIサーバーに接続できませんでした。しばらくしてから再度お試しください。']);
        }

        // HTTPステータスが4xx/5xxの場合はエラーメッセージを返す
        if ($response->failed()) {
            return back()->withInput()->withErrors(['api' => 'メール生成に失敗しました。しばらくしてから再度お試しください。']);
        }

        // レスポンスのテキストから件名と本文を正規表現で取り出す
        $text = $response->json('content.0.text');

        preg_match('/件名：(.+)/u', $text, $subjectMatch);
        preg_match('/本文：\s*([\s\S]+)/u', $text, $bodyMatch);

        $subject = trim($subjectMatch[1] ?? '');
        // 本文が取れなかった場合はレスポンス全体を表示するフォールバック
        $body = trim($bodyMatch[1] ?? $text);

        return view('mail-generator', compact('subject', 'body'))->with('input', $request->all());
    }
}
