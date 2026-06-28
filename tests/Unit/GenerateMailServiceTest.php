<?php

namespace Tests\Unit;

use App\Services\GenerateMailService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// GenerateMailServiceのユニットテスト
// API呼び出し・レスポンスパース・エラーハンドリングを検証する
class GenerateMailServiceTest extends TestCase
{
    // 各テスト前にダミーAPIキーをセットする
    // これがないとAPIキー未設定チェックで早期returnしてしまい、
    // API通信を模擬するテストが実際には実行されない
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-api-key']);
    }

    // テストで共通して使う入力データ
    // dot記法+デフォルト値でconfig未定義・空配列時のTypeError/undefined offsetを防ぐ
    private function validData(): array
    {
        return [
            'company_name'   => 'テスト株式会社',
            'visited_page'   => config('mail_options.visited_pages.0', '料金ページ'),
            'phase'          => config('mail_options.phases.0', '認知（初回訪問）'),
            'sender_name'    => '田中 太郎',
            'sender_company' => 'クラウドサーカス株式会社',
            // politeが存在すればそれを優先しconfigの並び順に依存しないようにする
            'tone'           => array_key_exists('polite', config('mail_options.tones', [])) ? 'polite' : (array_key_first(config('mail_options.tones', [])) ?? 'polite'),
        ];
    }

    // 正常なAPIレスポンスから件名・本文が返ること
    public function test_正常なレスポンスから件名と本文が返ること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "件名：テスト件名\n\n本文：\nテスト本文です。"],
                ],
            ], 200),
        ]);

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertSame('テスト件名', $result['subject']);
        $this->assertSame('テスト本文です。', $result['body']);
    }

    // APIキーが未設定の場合にerrorキーが返ること
    public function test_APIキー未設定のときerrorが返ること(): void
    {
        // このテストだけキーを空に上書きして未設定状態を再現する
        config(['services.anthropic.key' => '']);

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('error', $result);
    }

    // ConnectionExceptionが発生した場合にerrorキーが返ること
    public function test_接続エラー時にerrorが返ること(): void
    {
        Http::fake(function () {
            throw new ConnectionException('接続できませんでした');
        });

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('error', $result);
    }

    // APIが5xx系エラーを返した場合にerrorキーが返ること
    public function test_APIが失敗したときerrorが返ること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('error', $result);
    }

    // レスポンスのcontent.0.textがnullの場合にerrorキーが返ること
    public function test_レスポンスが不正な形式のときerrorが返ること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => null]],
            ], 200),
        ]);

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('error', $result);
    }

    // 件名フォーマットがない場合にerrorキーが返ること
    public function test_件名が含まれないレスポンスのときerrorが返ること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "こちらは件名なしの本文だけのテキストです。"],
                ],
            ], 200),
        ]);

        $result = (new GenerateMailService())->generate($this->validData());

        $this->assertArrayHasKey('error', $result);
    }

    // toneがcasualのときプロンプトにカジュアルが含まれること
    public function test_casualトーンのときカジュアルのプロンプトが送られること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "件名：テスト件名\n\n本文：\nテスト本文です。"],
                ],
            ], 200),
        ]);

        $data         = $this->validData();
        $data['tone'] = 'casual';

        (new GenerateMailService())->generate($data);

        Http::assertSent(function ($request) {
            // プロンプトにcasualの日本語ラベルが含まれること
            // デフォルトを実際のラベル値にすることでconfig未定義時も空文字にならず検証が常に有効になる
            $label = (string) config('mail_options.tones.casual', 'カジュアル（親しみやすい）');
            return str_contains($request->data()['messages'][0]['content'], $label);
        });
    }

    // toneに未知のキーを渡したとき、任意文字列がプロンプトに混入せずconfigの先頭ラベルへフォールバックすること
    public function test_不正なtoneキーのときデフォルトラベルがプロンプトに使われること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "件名：テスト件名\n\n本文：\nテスト本文です。"],
                ],
            ], 200),
        ]);

        $data         = $this->validData();
        $data['tone'] = 'invalid_tone_key';

        (new GenerateMailService())->generate($data);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];
            // サービス側のデフォルトはpoliteラベル優先なのでpoliteのラベルを明示参照する
            $expectedLabel = (string) config('mail_options.tones.polite', '丁寧（ビジネスフォーマル）');
            // 未知キー自体がプロンプトに含まれないこと
            $this->assertStringNotContainsString('invalid_tone_key', $content);
            // politeラベルがフォールバックとしてプロンプトに含まれること
            return str_contains($content, $expectedLabel);
        });
    }

    // company_nameが空のとき「相手の会社名：不明」がプロンプトに含まれること
    public function test_会社名未入力のとき不明がプロンプトに含まれること(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "件名：テスト件名\n\n本文：\nテスト本文です。"],
                ],
            ], 200),
        ]);

        $data                 = $this->validData();
        $data['company_name'] = '';

        (new GenerateMailService())->generate($data);

        Http::assertSent(function ($request) {
            return str_contains($request->data()['messages'][0]['content'], '相手の会社名：不明');
        });
    }
}
