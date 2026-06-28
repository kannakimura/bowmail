<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateMailRequest;
use App\Services\GenerateMailService;

// リードナーチャリングメール生成機能のコントローラー
// HTTPの入口として、入力受け取り・Service呼び出し・画面返却のみを担当する
class MailGeneratorController extends Controller
{
    // 入力フォーム画面を表示する
    // $inputを空配列で渡してビュー側の $input[...] ?? '' 参照で未定義エラーにならないようにする
    public function index()
    {
        return view('mail-generator', ['input' => []]);
    }

    // バリデーション済みの入力をServiceに渡してメールを生成し、結果をビューに返す
    public function generate(GenerateMailRequest $request, GenerateMailService $service)
    {
        $data   = $request->validated();
        $result = $service->generate($data);

        // Serviceがerrorキーを返した場合はフォームに戻す
        if (isset($result['error'])) {
            return back()->withInput()->withErrors(['api' => $result['error']]);
        }

        // inputにはvalidated()済みの$dataを渡す（_tokenなど未検証の値を含むall()は使わない）
        return view('mail-generator', [
            'subject' => $result['subject'],
            'body'    => $result['body'],
            'input'   => $data,
        ]);
    }
}
