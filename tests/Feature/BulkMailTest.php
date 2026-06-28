<?php

namespace Tests\Feature;

use Tests\TestCase;

// 一括メール生成機能のFeatureテスト
class BulkMailTest extends TestCase
{
    // GET /bulk でアップロード画面が表示されること
    public function test_アップロード画面が表示されること(): void
    {
        $response = $this->get('/bulk');

        $response->assertStatus(200);
        $response->assertSee('一括メール生成');
    }

    // アップロード画面にファイル選択フォームが含まれること
    public function test_アップロード画面にファイル選択フォームが含まれること(): void
    {
        $response = $this->get('/bulk');

        $response->assertSee('name="file"', false);
    }

    // アップロード画面に送信者情報の入力フォームが含まれること
    public function test_アップロード画面に送信者情報フォームが含まれること(): void
    {
        $response = $this->get('/bulk');

        $response->assertSee('name="sender_name"', false);
        $response->assertSee('name="sender_company"', false);
        $response->assertSee('name="tone"', false);
    }

    // 1件生成画面へ戻るリンクが含まれること
    public function test_アップロード画面にトップへ戻るリンクが含まれること(): void
    {
        $response = $this->get('/bulk');

        $response->assertSee(route('home'));
    }

    // フォームのactionがPOST /bulk/uploadを向いていること（405回避）
    public function test_フォームのactionがbulk_uploadルートを向いていること(): void
    {
        $response = $this->get('/bulk');

        $response->assertSee(route('bulk.upload'), false);
    }

    // POST /bulk/uploadが405ではなくリダイレクトを返すこと
    public function test_POST_bulk_uploadが405にならないこと(): void
    {
        $response = $this->post(route('bulk.upload'));

        // Phase 1-3実装前はアップロード画面へのリダイレクトが返ること
        $response->assertRedirect(route('bulk'));
    }

    // 必須フィールドにrequired属性が付いていること
    public function test_必須フィールドにrequired属性が付いていること(): void
    {
        $content = $this->get('/bulk')->content();

        $this->assertStringContainsString('name="file" accept=".xlsx" required', $content);
        $this->assertStringContainsString('name="sender_name"', $content);
        $this->assertMatchesRegularExpression('/name="sender_name"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="sender_company"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="tone"[^>]*required/', $content);
    }
}
