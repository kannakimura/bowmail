<?php

namespace Tests\Feature;

use Tests\TestCase;

// mail-generator.blade.phpのコピーボタン実装テスト
class CopyButtonTest extends TestCase
{
    // Clipboard API非対応環境向けのfallbackCopy関数が実装されていること
    public function test_コピーボタンにfallbackCopy関数が実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            'fallbackCopy',
            $content,
            'fallbackCopy関数が実装されていません'
        );
    }

    // navigator.clipboardの存在チェックが実装されていること（非セキュアコンテキスト対策）
    public function test_clipboardAPIの存在チェックが実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            'navigator.clipboard && navigator.clipboard.writeText',
            $content,
            'Clipboard APIの存在チェックが実装されていません'
        );
    }

    // コピー失敗時にユーザーへフィードバックするメッセージが実装されていること
    public function test_コピー失敗時のフィードバックが実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            'コピー失敗',
            $content,
            'コピー失敗時のフィードバックメッセージが実装されていません'
        );
    }

    // execCommand('copy')がフォールバックとして使われていること
    public function test_execCommandがフォールバックとして実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            "execCommand('copy')",
            $content,
            "execCommand('copy')フォールバックが実装されていません"
        );
    }

    // finallyブロックでtextareaが必ず除去されること（例外時のDOMリーク防止）
    public function test_fallbackCopyのfinallyでtextareaが除去されること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            'finally',
            $content,
            'finallyブロックが実装されていません'
        );

        $this->assertStringContainsString(
            'removeChild(textarea)',
            $content,
            'finallyブロック内でtextareaのremoveChildが呼ばれていません'
        );
    }

    // 再生成ボタンがrequestSubmitとsubmitのフォールバックを実装していること
    public function test_再生成ボタンにrequestSubmitフォールバックが実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            'form.requestSubmit',
            $content,
            'requestSubmitの呼び出しが実装されていません'
        );

        $this->assertStringContainsString(
            'form.submit()',
            $content,
            'requestSubmit非対応環境向けのsubmit()フォールバックが実装されていません'
        );
    }

    // toneフィールドに@errorディレクティブが設定されていること
    public function test_toneフィールドにエラー表示が実装されていること(): void
    {
        $content = file_get_contents(resource_path('views/mail-generator.blade.php'));

        $this->assertStringContainsString(
            "@error('tone')",
            $content,
            "@error('tone')が実装されていません"
        );
    }
}
