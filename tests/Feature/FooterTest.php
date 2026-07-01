<?php

namespace Tests\Feature;

use App\Http\Controllers\FooterController;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FooterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // テスト前にフッターファイルを削除してクリーンな状態にする
        Storage::delete('footer.json');
    }

    public function test_フッター登録画面を表示できる(): void
    {
        $response = $this->get('/footer');
        $response->assertStatus(200);
        $response->assertSee('メールフッター登録');
    }

    public function test_フッターを保存すると成功メッセージとともにリダイレクトされる(): void
    {
        $footer = "ーーー\n株式会社テスト\nテスト 太郎\nーーー";

        $response = $this->post('/footer', ['footer_text' => $footer]);

        $response->assertRedirect('/footer');
        $response->assertSessionHas('success');
    }

    public function test_保存したフッターが画面に表示される(): void
    {
        $footer = "テスト株式会社　営業部\nテスト 太郎";

        $this->post('/footer', ['footer_text' => $footer]);
        $response = $this->get('/footer');

        $response->assertSee($footer, escape: false);
    }

    public function test_loadFooterはファイルがない場合空文字を返す(): void
    {
        $this->assertSame('', FooterController::loadFooter());
    }

    public function test_loadFooterは保存済みフッターを返す(): void
    {
        $footer = "テスト署名";
        $this->post('/footer', ['footer_text' => $footer]);

        $this->assertSame($footer, FooterController::loadFooter());
    }

    public function test_フッターは1000文字を超えるとバリデーションエラー(): void
    {
        $response = $this->post('/footer', ['footer_text' => str_repeat('あ', 1001)]);
        $response->assertSessionHasErrors('footer_text');
    }

    public function test_フッターを空で保存できる(): void
    {
        $response = $this->post('/footer', ['footer_text' => '']);
        $response->assertRedirect('/footer');
        $this->assertSame('', FooterController::loadFooter());
    }
}
