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

    // バリデーション済みの入力をServiceに渡してメールを生成し、PRGパターンでリダイレクトする
    // POSTのまま view() を返すとリロード時にAPIが再消費されるため、成功時はGETへリダイレクトする
    public function generate(GenerateMailRequest $request, GenerateMailService $service)
    {
        $data   = $request->validated();
        $result = $service->generate($data);

        // Serviceがerrorキーを返した場合はフォームに戻す
        if (isset($result['error'])) {
            return back()->withInput()->withErrors(['api' => $result['error']]);
        }

        // 生成結果とフォーム入力値をセッションflashに保存してGETへリダイレクトする（PRGパターン）
        // inputにはvalidated()済みの$dataを渡す（_tokenなど未検証の値を含むall()は使わない）
        return redirect()->route('generate.result')->with([
            'subject' => $result['subject'],
            'body'    => $result['body'],
            'input'   => $data,
        ]);
    }

    // PRGのGETエンドポイント：セッションのflashから生成結果を受け取って表示する
    // リロードしてもセッションflashは消えるためAPIの再消費が起きない
    public function result()
    {
        return view('mail-generator', [
            'subject' => session('subject'),
            'body'    => session('body'),
            'input'   => session('input', []),
        ]);
    }
}
