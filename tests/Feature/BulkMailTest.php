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

    // POST /bulk/upload後に入力値がold()で保持されてビューに反映されること（withInput確認）
    public function test_POST_bulk_upload後に入力値がビューに保持されること(): void
    {
        // followingRedirects()でリダイレクト先まで追従し、old()がビューに反映されることを確認する
        $response = $this->followingRedirects()->post(route('bulk.upload'), [
            'sender_name'    => '田中 太郎',
            'sender_company' => 'クラウドサーカス株式会社',
            'tone'           => 'polite',
        ]);

        $response->assertStatus(200);
        $response->assertSee('田中 太郎');
        $response->assertSee('クラウドサーカス株式会社');
    }

    // POST /bulk/uploadに6回連続リクエストすると429(Too Many Requests)になること
    public function test_POST_bulk_uploadに連続リクエストするとスロットリングされること(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('bulk.upload'));
        }

        $response = $this->post(route('bulk.upload'));
        $response->assertStatus(429);
    }

    // レンダリングされたアップロード画面のHTMLにインラインスタイルが残っていないこと
    public function test_アップロード画面にインラインスタイルが残っていないこと(): void
    {
        $html = $this->get('/bulk')->content();

        // style="..."形式のインライン指定がレンダリング結果に含まれないこと
        $this->assertDoesNotMatchRegularExpression('/<[^>]+style="[^"]*"[^>]*>/', $html);
    }

    // 必須フィールドにrequired属性とaccept属性が付いていること
    // 属性順に依存しないよう正規表現で同一タグ内に両属性が存在することを検証する
    public function test_必須フィールドにrequired属性が付いていること(): void
    {
        $content = $this->get('/bulk')->content();

        // name="file"タグにaccept=".xlsx"とrequiredが順不同で含まれること
        $fileInputHasRequired =
            (bool) preg_match('/name="file"[^>]*accept="\.xlsx"[^>]*required/', $content) ||
            (bool) preg_match('/name="file"[^>]*required[^>]*accept="\.xlsx"/', $content);
        $this->assertTrue($fileInputHasRequired, 'ファイル入力にaccept=".xlsx"とrequiredが付いていません');
        $this->assertMatchesRegularExpression('/name="sender_name"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="sender_company"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="tone"[^>]*required/', $content);
    }
}
