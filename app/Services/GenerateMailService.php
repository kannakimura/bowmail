<?php

namespace App\Services;

use App\Http\Controllers\FooterController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Claude APIを使ってリードナーチャリングメールを生成するサービス
// プロンプト組み立て・API呼び出し・レスポンスのパースを担当する
class GenerateMailService
{
    // 生成成功時は ['subject' => ..., 'body' => ...] を返す
    // 失敗時は ['error' => エラーメッセージ] を返す
    public function generate(array $data): array
    {
        // APIキーが未設定の場合はAPIを呼ばずにエラーを返す
        if (empty(config('services.anthropic.key'))) {
            return ['error' => 'AIサービスの設定が不完全です。管理者にお問い合わせください。'];
        }

        $prompt = $this->buildPrompt($data);

        try {
            // timeout(30)：30秒応答がなければConnectionExceptionを発生させてプロセスブロックを防ぐ
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                // モデル名はconfig経由で取得し、.envで環境ごとに切り替えられるようにする
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } catch (ConnectionException $e) {
            // タイムアウトや接続失敗の場合は500にせずエラーを返す
            return ['error' => 'AIサーバーに接続できませんでした。しばらくしてから再度お試しください。'];
        }

        // HTTPステータスが4xx/5xxの場合はエラーを返す
        if ($response->failed()) {
            return ['error' => 'メール生成に失敗しました。しばらくしてから再度お試しください。'];
        }

        return $this->parseResponse($response->json('content.0.text'));
    }

    // 入力データからClaude APIへ送るプロンプトを組み立てる
    private function buildPrompt(array $data): string
    {
        // toneキーを一度取り出してUndefined indexを防ぐ
        // バリデーション外から配列等が混入してもIllegal offset typeにならないよう文字列以外は空に落とす
        // 未知キー時は polite ラベルを優先し、polite が存在しない場合のみ先頭ラベルへフォールバックする
        $toneKey      = is_string($data['tone'] ?? null) ? $data['tone'] : '';
        $tones        = config('mail_options.tones', []);
        $defaultLabel = $tones['polite'] ?? (reset($tones) ?: '');
        $tone         = $tones[$toneKey] ?? $defaultLabel;

        // 会社名が入力されている場合とない場合でプロンプトの文言を分ける
        $companyName = $data['company_name'] ?? '';
        $companyLine = $companyName ? "相手の会社名：{$companyName}" : '相手の会社名：不明';

        return <<<PROMPT
あなたはBtoBマーケティング担当者のメール文章作成を支援するAIです。
以下の情報をもとに、リードナーチャリング用のメール件名と本文を1案作成してください。

{$companyLine}
訪問したページ：{$data['visited_page']}
検討フェーズ：{$data['phase']}
送信者名：{$data['sender_name']}
送信者会社名：{$data['sender_company']}
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
    }

    // APIレスポンスのテキストから件名と本文を取り出す
    private function parseResponse(mixed $text): array
    {
        // content.0.textがnullや文字列以外だった場合はエラーとして扱う
        if (!is_string($text) || $text === '') {
            return ['error' => 'AIからの応答が不正でした。しばらくしてから再度お試しください。'];
        }

        preg_match('/件名：(.+)/u', $text, $subjectMatch);
        preg_match('/本文：\s*([\s\S]+)/u', $text, $bodyMatch);

        $subject = trim($subjectMatch[1] ?? '');

        // 件名が取れなかった場合はAIの応答フォーマット不正としてエラーを返す
        if ($subject === '') {
            return ['error' => 'AIからの応答が想定外の形式でした。もう一度お試しください。'];
        }

        // 本文が取れなかった場合はレスポンス全体を表示するフォールバック
        $body = trim($bodyMatch[1] ?? $text);

        // 登録済みフッターがあれば本文末尾に追加する
        $footer = FooterController::loadFooter();
        if ($footer !== '') {
            $body .= "\n\n" . $footer;
        }

        return compact('subject', 'body');
    }
}
