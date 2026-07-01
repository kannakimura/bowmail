<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// メールフッターの登録・表示を担当するコントローラー
// フッター内容は storage/app/footer.json にJSON形式で永続保存する
class FooterController extends Controller
{
    private const STORAGE_KEY = 'footer.json';

    public function index()
    {
        $footer = $this->loadFooter();
        return view('footer', compact('footer'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'footer_text' => ['nullable', 'string', 'max:1000'],
        ]);

        Storage::put(self::STORAGE_KEY, json_encode([
            'text' => $request->input('footer_text', ''),
        ]));

        return redirect()->route('footer')->with('success', 'メールフッターを保存しました。');
    }

    // 保存済みフッターテキストを返す。未保存時は空文字を返す
    public static function loadFooter(): string
    {
        if (!Storage::exists(self::STORAGE_KEY)) {
            return '';
        }

        $data = json_decode(Storage::get(self::STORAGE_KEY), true);
        return $data['text'] ?? '';
    }
}
