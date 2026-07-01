<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MailFlow - 一括メール生成</title>
    <link rel="stylesheet" href="{{ asset('css/mailflow.css') }}">
</head>
<body>

<div class="header">
    <h1>MailFlow</h1>
    <span>一括メール生成</span>
    <a href="{{ asset('downloads/template.xlsx') }}" download class="header-template-link">テンプレートをダウンロード（.xlsx）</a>
</div>

<div class="container">

    <a href="{{ route('mail') }}" class="nav-link">1件生成はこちら →</a>

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
                    <input type="text" id="sender_company" name="sender_company" placeholder="例：テスト株式会社" value="{{ old('sender_company') }}" required>
                </div>

                <div class="form-group">
                    <label for="tone">メールのトーン <span class="required-mark">*</span></label>
                    <select id="tone" name="tone" required>
                        @php
                            // configの取得とデフォルトキー算出をループ外で1回だけ行いTypeErrorを防ぐ
                            // politeが存在すればそれをデフォルトにしconfigの並び順に依存しないようにする
                            $tones       = config('mail_options.tones', []);
                            $defaultTone = isset($tones['polite']) ? 'polite' : (array_key_first($tones) ?? '');
                        @endphp
                        @foreach($tones as $value => $label)
                            <option value="{{ $value }}" {{ old('tone', $defaultTone) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="btn">アップロードしてプレビュー</button>
        </form>
    </div>

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

</body>
</html>
