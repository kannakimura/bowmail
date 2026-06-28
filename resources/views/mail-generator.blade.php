<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BowMail - リードナーチャリングメール生成</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f5f7fa; color: #333; }

        .header {
            background: #1a56db;
            color: #fff;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header h1 { font-size: 20px; font-weight: 700; letter-spacing: 0.05em; }
        .header span { font-size: 13px; opacity: 0.75; }

        .container { max-width: 860px; margin: 40px auto; padding: 0 20px; }

        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            padding: 32px;
            margin-bottom: 24px;
        }
        .card h2 { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: #1a56db; border-left: 3px solid #1a56db; padding-left: 10px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 13px; font-weight: 500; color: #555; }
        input[type="text"], select {
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus, select:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
        }

        .btn {
            display: inline-block;
            padding: 11px 28px;
            background: #1a56db;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover { background: #1648c0; }
        .btn:disabled { background: #93b4f0; cursor: not-allowed; }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .result-label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .result-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .subject-box { margin-bottom: 16px; }
        .copy-btn {
            margin-top: 8px;
            padding: 6px 14px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover { background: #e5e7eb; }

        .footer { text-align: center; font-size: 12px; color: #9ca3af; padding: 20px 0 40px; }
    </style>
</head>
<body>

<div class="header">
    <h1>BowMail</h1>
    <span>リードナーチャリングメール生成ツール</span>
</div>

<div class="container">

    @if ($errors->has('api'))
        <div class="error-box">{{ $errors->first('api') }}</div>
    @endif

    <div class="card">
        <h2>リード情報を入力</h2>
        <form method="POST" action="{{ route('generate') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label for="company_name">相手の会社名（任意）</label>
                    <input type="text" id="company_name" name="company_name" placeholder="例：株式会社〇〇" value="{{ old('company_name', $input['company_name'] ?? '') }}">
                </div>

                <div class="form-group">
                    <label for="visited_page">訪問したページ <span style="color:#e53e3e">*</span></label>
                    <select id="visited_page" name="visited_page">
                        <option value="">選択してください</option>
                        @foreach(['料金ページ', '導入事例ページ', '機能紹介ページ', '資料ダウンロードページ', 'お問い合わせページ（未送信）', 'トップページ'] as $page)
                            <option value="{{ $page }}" {{ old('visited_page', $input['visited_page'] ?? '') === $page ? 'selected' : '' }}>{{ $page }}</option>
                        @endforeach
                    </select>
                    @error('visited_page')<span style="color:#e53e3e;font-size:12px">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="phase">検討フェーズ <span style="color:#e53e3e">*</span></label>
                    <select id="phase" name="phase">
                        <option value="">選択してください</option>
                        @foreach(['認知（初回訪問）', '比較検討中', '導入検討中', '失注後フォロー'] as $p)
                            <option value="{{ $p }}" {{ old('phase', $input['phase'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                    @error('phase')<span style="color:#e53e3e;font-size:12px">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="tone">メールのトーン <span style="color:#e53e3e">*</span></label>
                    <select id="tone" name="tone">
                        <option value="polite" {{ old('tone', $input['tone'] ?? 'polite') === 'polite' ? 'selected' : '' }}>丁寧（ビジネスフォーマル）</option>
                        <option value="casual" {{ old('tone', $input['tone'] ?? '') === 'casual' ? 'selected' : '' }}>カジュアル（親しみやすい）</option>
                    </select>
                    @error('tone')<span style="color:#e53e3e;font-size:12px">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="sender_name">送信者名 <span style="color:#e53e3e">*</span></label>
                    <input type="text" id="sender_name" name="sender_name" placeholder="例：田中 太郎" value="{{ old('sender_name', $input['sender_name'] ?? '') }}">
                    @error('sender_name')<span style="color:#e53e3e;font-size:12px">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="sender_company">送信者の会社名 <span style="color:#e53e3e">*</span></label>
                    <input type="text" id="sender_company" name="sender_company" placeholder="例：クラウドサーカス株式会社" value="{{ old('sender_company', $input['sender_company'] ?? '') }}">
                    @error('sender_company')<span style="color:#e53e3e;font-size:12px">{{ $message }}</span>@enderror
                </div>
            </div>

            <div style="margin-top: 24px;">
                {{-- ローディング中はボタンを非活性にしてテキストを変える --}}
                <button type="submit" id="submit-btn" class="btn">メールを生成する</button>
            </div>
        </form>
    </div>

    @isset($subject, $body)
    <div class="card">
        <h2>生成されたメール</h2>
        <div class="subject-box">
            <div class="result-label">件名</div>
            <div class="result-box" id="subject-box">{{ $subject }}</div>
            <button class="copy-btn" onclick="copyText('subject-box', this)">コピー</button>
        </div>
        <div>
            <div class="result-label">本文</div>
            <div class="result-box" id="body-box">{{ $body }}</div>
            <button class="copy-btn" onclick="copyText('body-box', this)">コピー</button>
        </div>
        {{-- 再生成ボタン：requestSubmit()でsubmitイベントを発火してローディング表示も動かす --}}
        <div style="margin-top: 20px;">
            <button class="btn" style="background:#6b7280;" onclick="document.querySelector('form').requestSubmit(); return false;">もう一度生成する</button>
        </div>
    </div>
    @endisset

</div>

<div class="footer">BowMail — Powered by Claude AI</div>

<script>
// フォーム送信時にボタンをローディング状態にする
document.querySelector('form').addEventListener('submit', function () {
    const btn = document.getElementById('submit-btn');
    btn.textContent = '生成中...';
    btn.disabled = true;
});

// コピーボタンの処理
// navigator.clipboardはHTTPS/localhost以外の非セキュアコンテキストでは未定義になるため
// 使用できない場合はexecCommand('copy')にフォールバックする
function copyText(id, btn) {
    const text = document.getElementById(id).innerText;

    // Clipboard APIが使える場合（モダンブラウザ・セキュアコンテキスト）
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showCopied(btn);
        }).catch(() => {
            // Clipboard APIが拒否された場合（権限エラー等）はフォールバックを試みる
            fallbackCopy(text, btn);
        });
    } else {
        // Clipboard API非対応環境のフォールバック
        fallbackCopy(text, btn);
    }
}

// execCommandを使った旧来のコピー方法（非セキュアコンテキスト・古いブラウザ向け）
function fallbackCopy(text, btn) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    // 画面外に配置してスクロールを防ぐ
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);

    let success = false;
    try {
        textarea.focus();
        textarea.select();
        // execCommandは例外を投げることがあるためtry内で実行する
        success = document.execCommand('copy');
    } catch (e) {
        success = false;
    } finally {
        // 成功・失敗・例外いずれの場合もtextareaを必ず除去する
        document.body.removeChild(textarea);
    }

    if (success) {
        showCopied(btn);
    } else {
        btn.textContent = 'コピー失敗';
        setTimeout(() => btn.textContent = 'コピー', 2000);
    }
}

// コピー成功時のボタン表示を更新する
function showCopied(btn) {
    btn.textContent = 'コピーしました';
    setTimeout(() => btn.textContent = 'コピー', 2000);
}
</script>

</body>
</html>
