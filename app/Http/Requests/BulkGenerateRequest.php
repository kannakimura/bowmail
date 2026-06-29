<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// 一括生成実行リクエストのバリデーションを担当するFormRequest
// フォーム入力値はCSRFトークンのみのためrulesは空・セッション存在チェックをwithValidatorで行う
// バリデーション失敗時はアップロード画面へ戻して再アップロードを促す（プレビュー画面は$errorsを表示しないため）
class BulkGenerateRequest extends FormRequest
{
    // バリデーション失敗時のリダイレクト先をアップロード画面に固定する
    protected $redirectRoute = 'bulk';

    // 認可チェック：このアプリは認証不要のため常にtrueを返す
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    // セッションにプレビューデータが存在しない場合はバリデーションエラーを追加する
    // プレビューを経由せず直接POSTされた場合やセッション切れを検知してユーザーに再アップロードを促す
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty(session('bulk_rows')) || empty(session('bulk_input'))) {
                $validator->errors()->add(
                    'session',
                    'セッションが切れました。Excelファイルを再度アップロードしてください。'
                );
            }
        });
    }
}
