<?php

namespace Tests\Feature;

use App\Exceptions\EmptyRowsException;
use App\Exceptions\TooManyRowsException;
use App\Services\BulkExportService;
use App\Services\BulkGenerateService;
use App\Services\BulkImportService;
use Maatwebsite\Excel\Facades\Excel;
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
        // assertSee($label)だと送信者情報バッジ等に同名文字列があっても通るため
        // <th>タグを含めて検証しテーブルヘッダーとして表示されていることを保証する
        foreach ($columns as $label) {
            $this->assertMatchesRegularExpression(
                '/<th[^>]*>\s*' . preg_quote($label, '/') . '\s*<\/th>/',
                $response->content(),
                "テーブルヘッダー <th>{$label}</th> が見つかりません"
            );
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

    // 必須列が欠けているExcelをアップロードするとfileエラーが返ること
    public function test_必須列が欠けているExcelをアップロードするとfileエラーが返ること(): void
    {
        // ServiceがInvalidColumnExceptionを投げる状況をモックで再現する
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new \App\Exceptions\InvalidColumnException([config('bulk_import.columns.email')]));
        });

        // back()のリダイレクト先をbulkに確定するためfrom()を設定したうえでカウンタ方式のIPを使う
        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertRedirect(route('bulk'));
        $response->assertSessionHasErrors(['file']);
    }

    // 列構成不正のエラーメッセージがビューに表示されること
    public function test_列構成不正のエラーメッセージがビューに表示されること(): void
    {
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new \App\Exceptions\InvalidColumnException([config('bulk_import.columns.email')]));
        });

        // from()とfollowingRedirects()との組み合わせのためpostWithUniqueIp()を使わずカウンタ方式で一意なIPを生成する
        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->followingRedirects()
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(200);
        $response->assertSee('必須列が見つかりません');
    }

    // 列構成不正の場合はLog::errorが呼ばれないこと（ユーザー操作で解決できるためログ不要）
    public function test_列構成不正の場合はログが記録されないこと(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')->never();

        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new \App\Exceptions\InvalidColumnException([config('bulk_import.columns.email')]));
        });

        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());
    }

    // データ行0件のExcelをアップロードするとfileエラーが返ること
    public function test_データ行0件のExcelをアップロードするとfileエラーが返ること(): void
    {
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andThrow(new EmptyRowsException());
        });

        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertRedirect(route('bulk'));
        $response->assertSessionHasErrors(['file']);
    }

    // データ行0件のエラーメッセージがビューに表示されること
    public function test_データ行0件のエラーメッセージがビューに表示されること(): void
    {
        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andThrow(new EmptyRowsException());
        });

        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->followingRedirects()
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(200);
        $response->assertSee('データが1件もありません');
    }

    // データ行0件の場合はLog::errorが呼ばれないこと（ユーザー操作で解決できるためログ不要）
    public function test_データ行0件の場合はログが記録されないこと(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')->never();

        $this->mock(BulkImportService::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andThrow(new EmptyRowsException());
        });

        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());
    }

    // 上限件数超過のExcelをアップロードするとfileエラーが返ること
    public function test_上限件数超過のExcelをアップロードするとfileエラーが返ること(): void
    {
        $limit = config('bulk_import.max_rows', 500);
        $this->mock(BulkImportService::class, function ($mock) use ($limit) {
            $mock->shouldReceive('parse')->once()->andThrow(new TooManyRowsException($limit + 1, $limit));
        });

        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertRedirect(route('bulk'));
        $response->assertSessionHasErrors(['file']);
    }

    // 上限件数超過のエラーメッセージがビューに表示されること
    public function test_上限件数超過のエラーメッセージがビューに表示されること(): void
    {
        $limit = config('bulk_import.max_rows', 500);
        $this->mock(BulkImportService::class, function ($mock) use ($limit) {
            $mock->shouldReceive('parse')->once()->andThrow(new TooManyRowsException($limit + 1, $limit));
        });

        $ip       = '127.0.1.' . ((++self::$ipCounter % 253) + 1);
        $response = $this->from(route('bulk'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->followingRedirects()
            ->post(route('bulk.upload'), $this->validPayload());

        $response->assertStatus(200);
        // 上限件数はconfigから取得してハードコードを避ける
        $response->assertSee("一度にアップロードできるリードは{$limit}件まで");
    }

    // 上限件数超過の場合はLog::errorが呼ばれないこと（ユーザー操作で解決できるためログ不要）
    public function test_上限件数超過の場合はログが記録されないこと(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')->never();

        $limit = config('bulk_import.max_rows', 500);
        $this->mock(BulkImportService::class, function ($mock) use ($limit) {
            $mock->shouldReceive('parse')->once()->andThrow(new TooManyRowsException($limit + 1, $limit));
        });

        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());
    }

    // プレビュー画面に「N件のリードを読み込みました」が表示されること
    public function test_プレビュー画面に件数表示が含まれること(): void
    {
        $response = $this->withSession([
            'bulk_input' => ['sender_name' => 'テスト', 'sender_company' => 'テスト社', 'tone' => 'polite'],
            'bulk_rows'  => [
                ['company_name' => 'A社', 'email' => 'a@a.com', 'visited_page' => '料金ページ', 'phase' => '比較検討中'],
                ['company_name' => 'B社', 'email' => 'b@b.com', 'visited_page' => '料金ページ', 'phase' => '比較検討中'],
            ],
        ])->get(route('bulk.preview'));

        $response->assertStatus(200);
        $response->assertSee('2 件のリードを読み込みました');
    }

    // レンダリングされたアップロード画面のHTMLにインラインスタイルが残っていないこと
    public function test_アップロード画面にインラインスタイルが残っていないこと(): void
    {
        $html = $this->get('/bulk')->content();

        // style=".."またはstyle='..'のインライン指定がクォート種別によらず検出されること
        $this->assertDoesNotMatchRegularExpression('/<[^>]+style=(?:"[^"]*"|\'[^\']*\')[^>]*>/', $html);
    }

    // プレビュー画面に一括生成ボタンが表示されること
    public function test_プレビュー画面に一括生成ボタンが表示されること(): void
    {
        $this->mockBulkImportService();
        $this->from(route('bulk'));
        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response = $this->get(route('bulk.preview'));
        $response->assertStatus(200);
        $response->assertSee('一括生成する');
    }

    // プレビュー画面の一括生成フォームがbulk.generateへPOSTすること
    public function test_プレビュー画面の一括生成フォームが正しいactionを持つこと(): void
    {
        $this->mockBulkImportService();
        $this->from(route('bulk'));
        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        $response = $this->get(route('bulk.preview'));
        // action属性にbulk.generateのURLが含まれること
        $response->assertSee('action="' . route('bulk.generate') . '"', false);
    }

    // 一括生成のセッション存在チェック：セッションなし時はバリデーションエラーでアップロード画面へ戻る
    // セッションあり時（実フロー）はgenerate()まで到達する（Phase 2-4実装前はスタブとして501を返す）
    // セッションなしで一括生成POSTするとsessionエラーが返ること
    public function test_セッションなしで一括生成するとバリデーションエラーが返ること(): void
    {
        $this->postWithUniqueIp(route('bulk.generate'))
            ->assertSessionHasErrors(['session']);
    }

    // セッションなし時のバリデーション失敗リダイレクト先がアップロード画面であること
    public function test_セッションなしで一括生成するとアップロード画面へリダイレクトされること(): void
    {
        $this->postWithUniqueIp(route('bulk.generate'))
            ->assertRedirect(route('bulk'));
    }

    // 実フロー（upload→preview→generate）でflashデータがgenerate()まで届くこと
    public function test_実フローでセッションデータがgenerate処理まで届くこと(): void
    {
        // upload → preview → generate の実フローでflashデータがkeepされることを検証する
        $this->mockBulkImportService();
        $this->mock(BulkGenerateService::class, function ($mock) {
            $mock->shouldReceive('generateAll')->once()->andReturn(collect([
                ['subject' => '件名', 'body' => '本文'],
            ]));
        });

        $this->from(route('bulk'));
        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());

        // previewでkeepされることでflashデータが次リクエストまで延命する
        $this->get(route('bulk.preview'));

        // バリデーションエラーにならず生成処理が実行されbulk.resultへリダイレクトされること
        $this->postWithUniqueIp(route('bulk.generate'))
            ->assertRedirect(route('bulk.result'));
    }

    // 一括生成成功時にbulk_resultsがセッションに保存されること
    public function test_一括生成成功時にbulk_resultsがセッションに保存されること(): void
    {
        $this->mockBulkImportService();
        $this->mock(BulkGenerateService::class, function ($mock) {
            $mock->shouldReceive('generateAll')->once()->andReturn(collect([
                ['subject' => '件名', 'body' => '本文'],
            ]));
        });

        $this->from(route('bulk'));
        $this->postWithUniqueIp(route('bulk.upload'), $this->validPayload());
        $this->get(route('bulk.preview'));

        $this->postWithUniqueIp(route('bulk.generate'))
            ->assertSessionHas('bulk_results');
    }

    // 一括生成結果画面のテスト
    // 結果セッションありで結果画面にアクセスすると200で件名・本文が表示されること
    public function test_結果画面にアクセスすると生成結果が表示されること(): void
    {
        $this->withSession([
            'bulk_results' => [
                ['subject' => 'テスト件名', 'body' => 'テスト本文'],
            ],
        ]);

        $response = $this->get(route('bulk.result'));
        $response->assertStatus(200);
        $response->assertSee('テスト件名');
        $response->assertSee('テスト本文');
    }

    // 結果セッションなしで結果画面に直アクセスすると空状態のメッセージが表示されること
    public function test_結果セッションなしで結果画面にアクセスすると空状態が表示されること(): void
    {
        $response = $this->get(route('bulk.result'));
        $response->assertStatus(200);
        $response->assertSee('表示する生成結果がありません');
    }

    // 結果画面にExcelダウンロードボタンが表示されること
    public function test_結果画面にExcelダウンロードボタンが表示されること(): void
    {
        $response = $this->withSession([
            'bulk_results' => [
                ['subject' => 'テスト件名', 'body' => 'テスト本文'],
            ],
        ])->get(route('bulk.result'));

        $response->assertStatus(200);
        // href属性とテキスト「Excelダウンロード」が同一<a>タグ内に存在することを検証する
        $downloadUrl = route('bulk.download');
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="' . preg_quote($downloadUrl, '/') . '"[^>]*>\s*Excelダウンロード\s*<\/a>/',
            $response->content(),
            '「Excelダウンロード」ボタンのhrefが ' . $downloadUrl . ' になっていません'
        );
    }

    // 結果セッションなしの場合はダウンロードボタンが表示されないこと
    public function test_結果セッションなしの場合はダウンロードボタンが表示されないこと(): void
    {
        $response = $this->get(route('bulk.result'));

        $response->assertStatus(200);
        $response->assertDontSee('Excelダウンロード');
    }

    // API失敗行のエラーメッセージが結果画面に表示されること
    public function test_API失敗行のエラーメッセージが結果画面に表示されること(): void
    {
        $this->withSession([
            'bulk_results' => [
                ['error' => 'AIサーバーに接続できませんでした。'],
            ],
        ]);

        $response = $this->get(route('bulk.result'));
        $response->assertStatus(200);
        $response->assertSee('AIサーバーに接続できませんでした。');
    }

    // セッションなしでダウンロードするとアップロード画面へリダイレクトされること
    public function test_セッションなしでダウンロードするとアップロード画面へリダイレクトされること(): void
    {
        $this->get(route('bulk.download'))->assertRedirect(route('bulk'));
    }

    // セッションに結果がある場合にxlsxダウンロードレスポンスが返ること
    public function test_セッションあり時にダウンロードするとxlsxが返ること(): void
    {
        Excel::fake();

        $results = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名', 'body' => '本文'],
        ];

        // andReturnUsing で export() が実際に呼ばれたタイミングで Excel::download を実行することで
        // モック設定時の即時評価による誤検知を防ぎ、Controllerが正しい rows を渡したことも検証できる
        $this->mock(BulkExportService::class, function ($mock) use ($results) {
            $mock->shouldReceive('export')
                ->once()
                ->with($results)
                ->andReturnUsing(fn (array $rows) => Excel::download(
                    new \App\Exports\LeadResultExport($rows),
                    'bowmail_results.xlsx'
                ));
        });

        $this->withSession(['bulk_results' => $results])->get(route('bulk.download'));

        Excel::assertDownloaded('bowmail_results.xlsx');
    }

    // ダウンロードレスポンスのContent-TypeがExcel形式であること
    // Excel::fake()を使わず実際にExcelを生成してHTTPレスポンスのヘッダーを検証する
    public function test_ダウンロードレスポンスのContentTypeがExcel形式であること(): void
    {
        $results = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名', 'body' => '本文'],
        ];

        $response = $this->withSession(['bulk_results' => $results])
            ->get(route('bulk.download'));

        // Content-Typeにxlsx MIMEタイプが含まれること
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    // ダウンロードしたExcelの1行目がconfig定義の列ヘッダーと一致すること
    // Excel::fake()を使わず実際に生成したバイナリをPhpSpreadsheetで読み込んで検証する
    public function test_ダウンロードしたExcelの列ヘッダーがconfig定義と一致すること(): void
    {
        $results = [
            ['company_name' => 'A社', 'visited_page' => '料金ページ', 'phase' => '比較検討中', 'subject' => '件名', 'body' => '本文'],
        ];

        $response = $this->withSession(['bulk_results' => $results])
            ->get(route('bulk.download'));

        // BinaryFileResponseからファイルパスを取得してPhpSpreadsheetで直接読み込む
        $filePath = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $actualHeaders = $spreadsheet->getActiveSheet()->toArray()[0];

        $expectedHeaders = array_values(config('bulk_export.columns', []));
        $this->assertNotEmpty($expectedHeaders, 'bulk_export.columnsが空のためヘッダー検証が無効です');
        $this->assertSame($expectedHeaders, $actualHeaders);
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
