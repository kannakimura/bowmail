<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MailFlow - リードナーチャリングメール生成</title>
    <link rel="stylesheet" href="{{ asset('css/mailflow.css') }}">
</head>
<body>

<div class="header">
    <h1>MailFlow</h1>
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

                @php
                    // 各セレクトの選択肢をループ外で一度だけ取得しconfig未定義時のwarningを防ぐ
                    $visitedPages = config('mail_options.visited_pages', []);
                    $phases       = config('mail_options.phases', []);
                    $tones        = config('mail_options.tones', []);
                    // politeが存在すればそれをデフォルトにしconfigの並び順に依存しないようにする
                    $defaultTone  = isset($tones['polite']) ? 'polite' : (array_key_first($tones) ?? '');
                @endphp

                <div class="form-group">
                    <label for="visited_page">訪問したページ <span class="required-mark">*</span></label>
                    <select id="visited_page" name="visited_page" required>
                        <option value="">選択してください</option>
                        @foreach($visitedPages as $page)
                            <option value="{{ $page }}" {{ old('visited_page', $input['visited_page'] ?? '') === $page ? 'selected' : '' }}>{{ $page }}</option>
                        @endforeach
                    </select>
                    @error('visited_page')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="phase">検討フェーズ <span class="required-mark">*</span></label>
                    <select id="phase" name="phase" required>
                        <option value="">選択してください</option>
                        @foreach($phases as $p)
                            <option value="{{ $p }}" {{ old('phase', $input['phase'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                    @error('phase')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="tone">メールのトーン <span class="required-mark">*</span></label>
                    <select id="tone" name="tone" required>
                        @foreach($tones as $value => $label)
                            <option value="{{ $value }}" {{ old('tone', $input['tone'] ?? $defaultTone) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('tone')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="sender_name">送信者名 <span class="required-mark">*</span></label>
                    <input type="text" id="sender_name" name="sender_name" placeholder="例：田中 太郎" value="{{ old('sender_name', $input['sender_name'] ?? '') }}" required>
                    @error('sender_name')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="sender_company">送信者の会社名 <span class="required-mark">*</span></label>
                    <input type="text" id="sender_company" name="sender_company" placeholder="例：テスト株式会社" value="{{ old('sender_company', $input['sender_company'] ?? '') }}" required>
                    @error('sender_company')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="submit-area">
                {{-- ローディング中はボタンを非活性にしてテキストを変える --}}
                <button type="submit" id="submit-btn" class="btn">メールを生成する</button>
            </div>
        </form>
    </div>

    {{-- PRGパターン：セッションflashからnullでない値が渡ってきた場合のみ結果を表示する --}}
    @if(!is_null($subject ?? null) && !is_null($body ?? null))
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
        <div class="regenerate-area">
            {{-- requestSubmit()はsafari15.4未満等で未実装のためフォールバックを設ける --}}
            <button class="btn btn--gray" onclick="resubmitForm(); return false;">もう一度生成する</button>
        </div>
    </div>
    @endif

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

<script>
// 再生成ボタン：requestSubmit()でsubmitイベントを発火してローディング表示も動かす
// requestSubmitが未対応のブラウザではsubmit()にフォールバックする（ローディング表示なし）
function resubmitForm() {
    const form = document.querySelector('form');
    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

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
