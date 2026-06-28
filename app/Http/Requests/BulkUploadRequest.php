<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// 一括メール生成アップロードのバリデーションを担当するFormRequest
// Phase 1-3以降でsender_name等の項目も追加予定
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
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }
}
