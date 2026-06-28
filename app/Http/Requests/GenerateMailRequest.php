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
    // 選択肢はconfig/mail_options.phpで一元管理しここでは参照のみ行う
    // 自由入力フィールドはnot_regexで改行を禁止してプロンプト構造の破壊を防ぐ
    public function rules(): array
    {
        return [
            'company_name'   => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'visited_page'   => ['required', 'in:' . implode(',', config('mail_options.visited_pages'))],
            'phase'          => ['required', 'in:' . implode(',', config('mail_options.phases'))],
            'sender_name'    => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'sender_company' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'tone'           => ['required', 'in:' . implode(',', array_keys(config('mail_options.tones')))],
        ];
    }
}
