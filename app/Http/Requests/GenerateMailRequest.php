<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// メール生成フォームの入力バリデーションを担当するFormRequest
// Controllerにvalidateロジックをもたせないためにここへ分離する
class GenerateMailRequest extends FormRequest
{
    // 認可チェック：このアプリは認証不要のため常にtrueを返す
    public function authorize(): bool
    {
        return true;
    }

    // バリデーションルールを定義する
    // visited_page・phaseはin:でホワイトリスト検証してプロンプト注入を防ぐ
    // 自由入力フィールドはnot_regexで改行を禁止してプロンプト構造の破壊を防ぐ
    public function rules(): array
    {
        return [
            'company_name'   => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'visited_page'   => 'required|in:料金ページ,導入事例ページ,機能紹介ページ,資料ダウンロードページ,お問い合わせページ（未送信）,トップページ',
            'phase'          => 'required|in:認知（初回訪問）,比較検討中,導入検討中,失注後フォロー',
            'sender_name'    => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'sender_company' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'tone'           => 'required|in:polite,casual',
        ];
    }
}
