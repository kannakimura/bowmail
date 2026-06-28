<?php

namespace Tests\Feature;

use App\Services\BulkImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// 一括メール生成機能のFeatureテスト
class BulkMailTest extends TestCase
{
    // カウンタで決定的にIPを採番してランダム重複によるスロットリング干渉を防ぐ
    private static int $ipCounter = 0;

    private function postWithUniqueIp(string $url, array $data = []): \Illuminate\Testing\TestResponse
    {
        // %253で0〜252に循環し+1で1〜253に収めることで第4オクテットが0/255を超えない有効なIPv4を保証する
        $ip = '127.0.1.' . ((++self::$ipCounter % 253) + 1);

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post($url, $data);
    }

    // バリデーションを通過する有効なペイロードを返す
    private function validPayload(): array
    {
        return [
            'file'           => UploadedFile::fake()->create(
                'list.xlsx',
                100,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
            'sender_name'    => '田中 太郎',
            'sender_company' => 'クラウドサーカス株式会社',
            'tone'           => 'polite',
        ];
    }

    // BulkImportServiceをモックしてパース済み行のコレクションを返すよう設定する
    // 偽ファイルでも実際のExcelパースを行わずControllerの動作を検証できる
    private function mockBulkImportService(): void
    {
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturn(collect([
                    collect([
                        'company_name' => 'テスト株式会社',
                        'email'        => 'test@example.com',
                        'visited_page' => '料金ページ',
                        'phase'        => '比較検討中',
                    ]),
                ]));
        });
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

    // 有効なxlsxファイルをPOSTすると405にならず302リダイレクトが返ること
    public function test_POST_bulk_uploadが405にならないこと(): void
    {
        $this->mockBulkImportService();

        $response = $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(302);
    }

    // POST /bulk/upload後にflashセッションへ送信者情報が保存されること
    public function test_upload後にセッションにbulk_inputが保存されること(): void
    {
        $this->mockBulkImportService();

        $response = $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response->assertSessionHas('bulk_input', [
            'sender_name'    => '田中 太郎',
            'sender_company' => 'クラウドサーカス株式会社',
            'tone'           => 'polite',
        ]);
    }

    // POST /bulk/upload後にflashセッションへパース済み行データが保存されること
    public function test_upload後にセッションにbulk_rowsが保存されること(): void
    {
        $this->mockBulkImportService();

        $response = $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response->assertSessionHas('bulk_rows');
        $rows = $response->getSession()->get('bulk_rows');
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertSame('テスト株式会社', $rows[0]['company_name']);
        $this->assertSame('test@example.com', $rows[0]['email']);
    }

    // POST /bulk/upload後にbulk.previewへリダイレクトされること
    public function test_upload後にbulk_previewへリダイレクトされること(): void
    {
        $this->mockBulkImportService();

        $response = $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response->assertRedirect(route('bulk.preview'));
    }

    // GET /bulk/preview がセッションなしでも200を返すこと
    public function test_GET_bulk_previewが200を返すこと(): void
    {
        $response = $this->get(route('bulk.preview'));

        $response->assertStatus(200);
    }

    // パース中に例外が発生するとアップロード画面へ戻りfileエラーが表示されること
    // またスタックトレース付きでLog::error()が呼ばれることも検証する
    public function test_パース例外発生時にアップロード画面へ戻りエラーが表示されること(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                // exceptionキーに例外オブジェクトが渡されてスタックトレースが記録されること
                return $message === 'Excelパース失敗'
                    && isset($context['exception'])
                    && $context['exception'] instanceof \Throwable;
            });

        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new \RuntimeException('破損したファイルです'));
        });

        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertRedirect(route('bulk'));
        $response->assertSessionHasErrors(['file']);
    }

    // パース例外時のエラーメッセージが画面に表示されること
    public function test_パース例外時のエラーメッセージがビューに表示されること(): void
    {
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new \RuntimeException('破損したファイルです'));
        });

        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.11'])
            ->followingRedirects()
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(200);
        $response->assertSee('ファイルの読み込みに失敗しました');
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
        $payload['file'] = UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');

        $response = $this->postWithUniqueIp(route('bulk.upload'), $payload);

        $response->assertSessionHasErrors(['file']);
    }

    // 5MBを超えるファイルをアップロードするとバリデーションエラーになること
    public function test_5MBを超えるファイルをアップロードするとエラーになること(): void
    {
        $payload         = $this->validPayload();
        $payload['file'] = UploadedFile::fake()->create('large.xlsx', 6000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->postWithUniqueIp(route('bulk.upload'), $payload);

        $response->assertSessionHasErrors(['file']);
    }

    // POST /bulk/uploadに6回連続リクエストすると429(Too Many Requests)になること
    // 固定IPを使うことで他テストのリクエスト数に影響されない独立したレート検証を保証する
    public function test_POST_bulk_uploadに連続リクエストするとスロットリングされること(): void
    {
        // スロットリングはミドルウェアレベルで判定されるためServiceをモックして5回通過させる
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->times(5)
                ->andReturn(collect());
        });

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->post(route('bulk.upload'), $this->validPayload());
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('bulk.upload'), $this->validPayload());
        $response->assertStatus(429);
    }

    // GET /bulk/preview がセッションデータありで送信者情報を表示すること
    public function test_プレビュー画面が送信者情報を表示すること(): void
    {
        $response = $this->withSession([
            'bulk_input' => [
                'sender_name'    => '田中 太郎',
                'sender_company' => 'クラウドサーカス株式会社',
                'tone'           => 'polite',
            ],
            'bulk_rows' => [
                [
                    'company_name' => 'テスト株式会社',
                    'email'        => 'test@example.com',
                    'visited_page' => '料金ページ',
                    'phase'        => '比較検討中',
                ],
            ],
        ])->get(route('bulk.preview'));

        $response->assertStatus(200);
        $response->assertSee('田中 太郎');
        $response->assertSee('クラウドサーカス株式会社');
        // toneはconfigのラベル（丁寧（ビジネスフォーマル））が表示されること
        $response->assertSee('丁寧（ビジネスフォーマル）');
    }

    // GET /bulk/preview がセッションデータありでリード行をテーブル表示すること
    public function test_プレビュー画面がリード行をテーブル表示すること(): void
    {
        $response = $this->withSession([
            'bulk_input' => [
                'sender_name'    => '田中 太郎',
                'sender_company' => 'クラウドサーカス株式会社',
                'tone'           => 'polite',
            ],
            'bulk_rows' => [
                [
                    'company_name' => 'テスト株式会社',
                    'email'        => 'test@example.com',
                    'visited_page' => '料金ページ',
                    'phase'        => '比較検討中',
                ],
            ],
        ])->get(route('bulk.preview'));

        $response->assertStatus(200);
        $response->assertSee('テスト株式会社');
        $response->assertSee('test@example.com');
        $response->assertSee('料金ページ');
        $response->assertSee('比較検討中');
    }

    // GET /bulk/preview でセッションなし（直アクセス）の場合に空状態メッセージを表示すること
    public function test_プレビュー画面がセッションなしで空状態メッセージを表示すること(): void
    {
        $response = $this->get(route('bulk.preview'));

        $response->assertStatus(200);
        $response->assertSee('表示するデータがありません');
    }

    // GET /bulk/preview がconfig定義の列ヘッダーをテーブルに表示すること
    public function test_プレビュー画面がconfig定義の列ヘッダーを表示すること(): void
    {
        $columns = config('bulk_import.columns', []);
        // bulk_import.columnsが空だとforeachが回らず検証が無効になるため事前にガードする
        $this->assertNotEmpty($columns, 'bulk_import.columnsが空のためヘッダー検証が無効です');

        $response = $this->withSession([
            'bulk_input' => ['sender_name' => 'テスト', 'sender_company' => 'テスト社', 'tone' => 'polite'],
            'bulk_rows'  => [array_fill_keys(array_keys($columns), '')],
        ])->get(route('bulk.preview'));

        $response->assertStatus(200);
        foreach ($columns as $label) {
            $response->assertSee($label);
        }
    }

    // GET /bulk/preview に「やり直す」ボタンがあり /bulk へ戻れること
    // hrefとテキストが同一<a>タグ内に存在することを正規表現で検証し、
    // 上部ナビのリンクだけにroute('bulk')が残る場合でもテストが通らないことを保証する
    public function test_プレビュー画面にやり直すボタンが含まれること(): void
    {
        $response = $this->withSession([
            'bulk_input' => ['sender_name' => 'テスト', 'sender_company' => 'テスト社', 'tone' => 'polite'],
            'bulk_rows'  => [['company_name' => 'A', 'email' => 'a@a.com', 'visited_page' => 'x', 'phase' => 'y']],
        ])->get(route('bulk.preview'));

        $response->assertStatus(200);
        // href属性とテキスト「やり直す」が同一<a>タグ内に存在することを検証する
        $bulkUrl = route('bulk');
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="' . preg_quote($bulkUrl, '/') . '"[^>]*>\s*やり直す\s*<\/a>/',
            $response->content(),
            '「やり直す」ボタンのhrefが ' . $bulkUrl . ' になっていません'
        );
    }

    // レンダリングされたアップロード画面のHTMLにインラインスタイルが残っていないこと
    public function test_アップロード画面にインラインスタイルが残っていないこと(): void
    {
        $html = $this->get('/bulk')->content();

        // style=".."またはstyle='..'のインライン指定がクォート種別によらず検出されること
        $this->assertDoesNotMatchRegularExpression('/<[^>]+style=(?:"[^"]*"|\'[^\']*\')[^>]*>/', $html);
    }

    // 必須フィールドにrequired属性とaccept属性が付いていること
    // 属性順に依存しないよう正規表現で同一タグ内に両属性が存在することを検証する
    public function test_必須フィールドにrequired属性が付いていること(): void
    {
        $content = $this->get('/bulk')->content();

        // lookaheadで属性順非依存・required(?:="[^"]*")?で値付き形式(required="required"等)も許容
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\sname="file")(?=[^>]*\saccept="\.xlsx")(?=[^>]*\srequired(?:="[^"]*")?(?=[\s\/>]))[^>]*>/',
            $content,
            'ファイル入力にaccept=".xlsx"とrequiredが付いていません'
        );
        foreach (['sender_name', 'sender_company', 'tone'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<(?:input|select|textarea)(?=[^>]*\sname="' . $field . '")(?=[^>]*\srequired(?:="[^"]*")?(?=[\s\/>]))[^>]*>/',
                $content,
                "{$field} フィールドにrequired属性が付いていません"
            );
        }
    }
}
