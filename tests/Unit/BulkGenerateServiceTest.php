<?php

namespace Tests\Unit;

use App\Services\BulkGenerateService;
use App\Services\GenerateMailService;
use Illuminate\Support\Collection;
use Tests\TestCase;

// BulkGenerateServiceのユニットテスト
class BulkGenerateServiceTest extends TestCase
{
    // 2件のリードを渡すと件数分だけGenerateMailServiceが呼ばれCollectionで返ること
    public function test_リード件数分だけ生成が呼ばれCollectionが返ること(): void
    {
        $rows = [
            ['company_name' => 'テスト株式会社',   'email' => 'a@example.com', 'visited_page' => '料金ページ',    'phase' => '比較検討中'],
            ['company_name' => 'サンプル合同会社', 'email' => 'b@example.com', 'visited_page' => '導入事例ページ', 'phase' => '導入検討中'],
        ];
        $input = ['sender_name' => '田中 太郎', 'sender_company' => 'クラウドサーカス株式会社', 'tone' => 'polite'];

        $mockService = $this->mock(GenerateMailService::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->twice()
                ->andReturn(['subject' => '件名', 'body' => '本文']);
        });

        $service = new BulkGenerateService($mockService);
        $result  = $service->generateAll($rows, $input);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    // GenerateMailServiceにリード情報と送信者情報がマージされて渡ること
    public function test_リード情報と送信者情報がマージされてGenerateMailServiceに渡ること(): void
    {
        $rows  = [['company_name' => 'テスト株式会社', 'email' => 'a@example.com', 'visited_page' => '料金ページ', 'phase' => '比較検討中']];
        $input = ['sender_name' => '田中 太郎', 'sender_company' => 'クラウドサーカス株式会社', 'tone' => 'polite'];

        $expected = array_merge($rows[0], $input);

        $mockService = $this->mock(GenerateMailService::class, function ($mock) use ($expected) {
            $mock->shouldReceive('generate')
                ->once()
                ->with($expected)
                ->andReturn(['subject' => '件名', 'body' => '本文']);
        });

        $service = new BulkGenerateService($mockService);
        $service->generateAll($rows, $input);
    }

    // GenerateMailServiceがエラーを返した行もCollectionに含まれること
    public function test_API失敗行もCollectionに含まれること(): void
    {
        $rows  = [['company_name' => 'テスト株式会社', 'email' => 'a@example.com', 'visited_page' => '料金ページ', 'phase' => '比較検討中']];
        $input = ['sender_name' => '田中 太郎', 'sender_company' => 'クラウドサーカス株式会社', 'tone' => 'polite'];

        $mockService = $this->mock(GenerateMailService::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn(['error' => 'AIサーバーに接続できませんでした。']);
        });

        $service = new BulkGenerateService($mockService);
        $result  = $service->generateAll($rows, $input);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('error', $result->first());
    }
}
