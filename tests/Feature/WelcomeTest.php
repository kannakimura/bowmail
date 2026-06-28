<?php

namespace Tests\Feature;

use Tests\TestCase;

// welcome.blade.phpのセキュリティ属性テスト
class WelcomeTest extends TestCase
{
    // target="_blank"リンクにrel="noopener noreferrer"が付いていること（reverse tabnabbing対策）
    public function test_外部リンクにnoopener_noreferrerが設定されていること(): void
    {
        $content = file_get_contents(resource_path('views/welcome.blade.php'));

        // target="_blank"が存在するリンクはすべてrel="noopener noreferrer"を持つこと
        preg_match_all('/<a [^>]*target="_blank"[^>]*>/i', $content, $matches);

        $this->assertNotEmpty($matches[0], 'target="_blank"のリンクが1件も見つかりません');

        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString(
                'rel="noopener noreferrer"',
                $tag,
                "次のリンクにrel=\"noopener noreferrer\"がありません: {$tag}"
            );
        }
    }
}
