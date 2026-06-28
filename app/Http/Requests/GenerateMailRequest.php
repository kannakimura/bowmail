<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    // visited_page・phaseはRule::in()でホワイトリスト検証してプロンプト注入を防ぐ
    // 選択肢はconfig/mail_options.phpで一元管理しここでは参照のみ行う
    // Rule::in()に配列を渡すことでカンマを含む選択肢でも安全に動作する
    // 自由入力フィールドはnot_regexで改行を禁止してプロンプト構造の破壊を防ぐ
    public function rules(): array
    {
        return [
            'company_name'   => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            // string を先に検証してスカラー型を強制し、配列等が後続のRule::in/Serviceに渡るのを防ぐ
            'visited_page'   => ['required', 'string', Rule::in(config('mail_options.visited_pages', []))],
            'phase'          => ['required', 'string', Rule::in(config('mail_options.phases', []))],
            'sender_name'    => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'sender_company' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'tone'           => ['required', 'string', Rule::in(array_keys(config('mail_options.tones', [])))],
        ];
    }
}
