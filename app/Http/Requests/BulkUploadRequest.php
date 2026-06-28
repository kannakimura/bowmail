<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// 一括メール生成アップロードのバリデーションを担当するFormRequest
class BulkUploadRequest extends FormRequest
{
    // 認可チェック：このアプリは認証不要のため常にtrueを返す
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // xlsxのみ許可・最大5MB・必須
            'file'           => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            // 送信者情報・トーンはUIと同様に必須・GenerateMailRequestと同じ制約を適用する
            'sender_name'    => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'sender_company' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'tone'           => ['required', 'in:polite,casual'],
        ];
    }
}
