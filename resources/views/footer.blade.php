<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>MailFlow - メールフッター登録</title>
    <link rel="stylesheet" href="{{ asset('css/mailflow.css') }}">
</head>
<body>

<div class="header">
    <h1>MailFlow</h1>
    <span>メールフッター登録</span>
    <a href="{{ asset('downloads/template.xlsx') }}" download class="header-template-link">テンプレートをダウンロード（.xlsx）</a>
</div>

<div class="container">

    <a href="{{ route('bulk') }}" class="nav-link">← 一括生成に戻る</a>

    @if (session('success'))
        <div class="success-box">{{ session('success') }}</div>
    @endif

    <div class="card">
        <h2>メールフッター登録</h2>
        <p class="hint" style="margin-bottom:16px;">生成されたメール本文の末尾に自動で追加されます。署名・連絡先などを登録してください。</p>

        <form method="POST" action="{{ route('footer.save') }}">
            @csrf
            <div class="form-group">
                <label for="footer_text">フッター内容</label>
                <textarea
                    id="footer_text"
                    name="footer_text"
                    rows="8"
                    placeholder="例：&#13;&#10;ーーーーーーーーーーーーー&#13;&#10;株式会社テスト　営業部&#13;&#10;テスト　太郎&#13;&#10;Mail test@example.com&#13;&#10;Tel 03-123-1234&#13;&#10;ーーーーーーーーーーーーー"
                    style="width:100%; font-family:monospace; resize:vertical;"
                >{{ $footer }}</textarea>
                @error('footer_text')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="btn-row" style="margin-top:16px;">
                <button type="submit" class="btn btn--primary">保存する</button>
            </div>
        </form>
    </div>

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

</body>
</html>
