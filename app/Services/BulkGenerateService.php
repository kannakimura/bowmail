<?php

namespace App\Services;

use Illuminate\Support\Collection;

// セッションのリードデータを受け取りClaude APIを順次呼び出してメール件名・本文を生成するService
// 生成成功行は ['subject' => ..., 'body' => ...] 、失敗行は ['error' => ...] の配列を返す
// API呼び出しの詳細はGenerateMailServiceに委譲し、このServiceはリスト処理に専念する
class BulkGenerateService
{
    public function __construct(private readonly GenerateMailService $generateMailService) {}

    // リード一覧と送信者情報を受け取り各行のメール生成結果を返す
    // 各リード行は ['company_name', 'email', 'visited_page', 'phase'] のキーを持つ配列を期待する
    // 送信者情報は ['sender_name', 'sender_company', 'tone'] のキーを持つ配列を期待する
    // 現状はWebリクエスト内で件数分だけAPIを同期呼び出しするため、最大500件×timeout(30s)でリクエストが長時間化するリスクがある
    // 件数が増えて問題になる場合はJobキューによる非同期化やチャンク処理＋進捗表示への移行を検討すること
    public function generateAll(array $rows, array $input): Collection
    {
        return collect($rows)->map(function (array $row) use ($input) {
            // GenerateMailServiceが期待するデータ形式に合わせてリード情報と送信者情報をマージする
            $data   = array_merge($row, $input);
            $result = $this->generateMailService->generate($data);
            // 結果画面で送信先会社名を表示するためにリード情報を結果に付加する
            return array_merge(['company_name' => $row['company_name'] ?? ''], $result);
        });
    }
}
