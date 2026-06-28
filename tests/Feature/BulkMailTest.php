<?php

namespace Tests\Feature;

use Tests\TestCase;

// 一括メール生成機能のFeatureテスト
class BulkMailTest extends TestCase
{
    // カウンタで決定的にIPを採番してランダム重複によるスロットリング干渉を防ぐ
    private static int $ipCounter = 0;

    private function postWithUniqueIp(string $url, array $data = []): \Illuminate\Testing\TestResponse
    {
        $ip = '127.0.1.' . (++self::$ipCounter);

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post($url, $data);
    }

    // バリデーションを通過する有効なペイロードを返す
    private function validPayload(): array
    {
        return [
            'file'           => \Illuminate\Http\UploadedFile::fake()->create(
                'list.xlsx',
                100,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
            'sender_name'    => '田中 太郎',
            'sender_company' => 'クラウドサーカス株式会社',
            'tone'           => 'polite',
        ];
    }

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

    // 有効なxlsxファイルをPOSTするとアップロード画面へリダイレクトされること（405にならないこと）
    public function test_POST_bulk_uploadが405にならないこと(): void
    {
        $response = $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        // Phase 1-3実装前はアップロード画面へのリダイレクトが返ること
        $response->assertRedirect(route('bulk'));
    }

    // POST /bulk/upload後に入力値がold()で保持されてビューに反映されること（withInput確認）
    // validPayload()でバリデーションを通過させController側のwithInput()を検証する
    public function test_POST_bulk_upload後に入力値がビューに保持されること(): void
    {
        // followingRedirects()でリダイレクト先まで追従し、old()がビューに反映されることを確認する
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->followingRedirects()
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(200);
        $response->assertSee('田中 太郎');
        $response->assertSee('クラウドサーカス株式会社');
    }

    // ファイルを添付せずにPOSTするとバリデーションエラーになること
    public function test_ファイル未添付でPOSTするとバリデーションエラーになること(): void
    {
        $response = $this->postWithUniqueIp(route('bulk.upload'));

        $response->assertSessionHasErrors(['file']);
    }

    // バリデーションエラー時にbulk画面へリダイレクトされエラーが表示されること
    public function test_バリデーションエラー時にビューにエラーメッセージが表示されること(): void
    {
        // refererをbulkに設定してback()が正しく/bulkへ戻ることを保証する
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.2'])
            ->followingRedirects()
            ->post(route('bulk.upload'));

        $response->assertStatus(200);
        $response->assertSee('error-box', false);
    }

    // xlsx以外のファイルをアップロードするとバリデーションエラーになること
    public function test_xlsx以外のファイルをアップロードするとエラーになること(): void
    {
        $payload         = $this->validPayload();
        $payload['file'] = \Illuminate\Http\UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');

        $response = $this->postWithUniqueIp(route('bulk.upload'), $payload);

        $response->assertSessionHasErrors(['file']);
    }

    // 5MBを超えるファイルをアップロードするとバリデーションエラーになること
    public function test_5MBを超えるファイルをアップロードするとエラーになること(): void
    {
        $payload         = $this->validPayload();
        $payload['file'] = \Illuminate\Http\UploadedFile::fake()->create('large.xlsx', 6000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->postWithUniqueIp(route('bulk.upload'), $payload);

        $response->assertSessionHasErrors(['file']);
    }

    // POST /bulk/uploadに6回連続リクエストすると429(Too Many Requests)になること
    // 固定IPを使うことで他テストのリクエスト数に影響されない独立したレート検証を保証する
    public function test_POST_bulk_uploadに連続リクエストするとスロットリングされること(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->post(route('bulk.upload'), $this->validPayload());
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('bulk.upload'), $this->validPayload());
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

        // lookaheadで属性順非依存・\srequired(?=[\s\/>])でハイフン付き属性への誤マッチを防ぐ
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\sname="file")(?=[^>]*\saccept="\.xlsx")(?=[^>]*\srequired(?=[\s\/>]))[^>]*>/',
            $content,
            'ファイル入力にaccept=".xlsx"とrequiredが付いていません'
        );
        foreach (['sender_name', 'sender_company', 'tone'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<(?:input|select|textarea)(?=[^>]*\sname="' . $field . '")(?=[^>]*\srequired(?=[\s\/>]))[^>]*>/',
                $content,
                "{$field} フィールドにrequired属性が付いていません"
            );
        }
    }
}
