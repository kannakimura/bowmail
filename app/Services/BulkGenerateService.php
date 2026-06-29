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
    public function generateAll(array $rows, array $input): Collection
    {
        return collect($rows)->map(function (array $row) use ($input) {
            // GenerateMailServiceが期待するデータ形式に合わせてリード情報と送信者情報をマージする
            $data = array_merge($row, $input);
            return $this->generateMailService->generate($data);
        });
    }
}
