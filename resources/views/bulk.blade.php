<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BowMail - 一括メール生成</title>
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
        label { font-size: 13px; font-weight: 500; color: #555; }
        input[type="text"], select, input[type="file"] {
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
        input[type="file"] { padding: 7px 12px; background: #f9fafb; cursor: pointer; }

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

        .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }

        .nav-link {
            display: inline-block;
            margin-bottom: 24px;
            font-size: 13px;
            color: #1a56db;
            text-decoration: none;
        }
        .nav-link:hover { text-decoration: underline; }

        .footer { text-align: center; font-size: 12px; color: #9ca3af; padding: 20px 0 40px; }
    </style>
</head>
<body>

<div class="header">
    <h1>BowMail</h1>
    <span>一括メール生成</span>
</div>

<div class="container">

    <a href="{{ route('home') }}" class="nav-link">← 1件生成に戻る</a>

    <div class="card">
        <h2>Excelファイルをアップロード</h2>
        <form method="POST" action="#" enctype="multipart/form-data">
            @csrf
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="file">リストファイル（.xlsx）<span style="color:#e53e3e"> *</span></label>
                <input type="file" id="file" name="file" accept=".xlsx">
                <span class="hint">列構成：会社名 / メールアドレス / 訪問ページ / フェーズ</span>
            </div>

            <div class="form-grid" style="margin-bottom: 24px;">
                <div class="form-group">
                    <label for="sender_name">送信者名 <span style="color:#e53e3e">*</span></label>
                    <input type="text" id="sender_name" name="sender_name" placeholder="例：田中 太郎" value="{{ old('sender_name') }}">
                </div>

                <div class="form-group">
                    <label for="sender_company">送信者の会社名 <span style="color:#e53e3e">*</span></label>
                    <input type="text" id="sender_company" name="sender_company" placeholder="例：クラウドサーカス株式会社" value="{{ old('sender_company') }}">
                </div>

                <div class="form-group">
                    <label for="tone">メールのトーン <span style="color:#e53e3e">*</span></label>
                    <select id="tone" name="tone">
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
