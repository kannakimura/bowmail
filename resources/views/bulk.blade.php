<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BowMail - 一括メール生成</title>
    <link rel="stylesheet" href="{{ asset('css/bowmail.css') }}">
</head>
<body>

<div class="header">
    <h1>BowMail</h1>
    <span>一括メール生成</span>
</div>

<div class="container">

    <a href="{{ route('home') }}" class="nav-link">← 1件生成に戻る</a>

    @if ($errors->any())
        <div class="error-box">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <h2>Excelファイルをアップロード</h2>
        <form method="POST" action="{{ route('bulk.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group form-group--mb20">
                <label for="file">リストファイル（.xlsx）<span class="required-mark"> *</span></label>
                <input type="file" id="file" name="file" accept=".xlsx" required>
                @error('file')<span class="field-error">{{ $message }}</span>@enderror
                <span class="hint">列構成：会社名 / メールアドレス / 訪問ページ / フェーズ</span>
            </div>

            <div class="form-grid form-grid--mb24">
                <div class="form-group">
                    <label for="sender_name">送信者名 <span class="required-mark">*</span></label>
                    <input type="text" id="sender_name" name="sender_name" placeholder="例：田中 太郎" value="{{ old('sender_name') }}" required>
                </div>

                <div class="form-group">
                    <label for="sender_company">送信者の会社名 <span class="required-mark">*</span></label>
                    <input type="text" id="sender_company" name="sender_company" placeholder="例：クラウドサーカス株式会社" value="{{ old('sender_company') }}" required>
                </div>

                <div class="form-group">
                    <label for="tone">メールのトーン <span class="required-mark">*</span></label>
                    <select id="tone" name="tone" required>
                        <option value="polite" {{ old('tone', 'polite') === 'polite' ? 'selected' : '' }}>丁寧（ビジネスフォーマル）</option>
                        <option value="casual" {{ old('tone') === 'casual' ? 'selected' : '' }}>カジュアル（親しみやすい）</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn">アップロードしてプレビュー</button>
        </form>
    </div>

</div>

<div class="footer">BowMail — Powered by Claude AI</div>

</body>
</html>
