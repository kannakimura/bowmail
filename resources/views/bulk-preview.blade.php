<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>MailFlow - アップロード内容の確認</title>
    <link rel="stylesheet" href="{{ asset('css/mailflow.css') }}">
</head>
<body>

<div class="header">
    <h1>MailFlow</h1>
    <span>アップロード内容の確認</span>
</div>

<div class="container">

    <a href="{{ route('bulk') }}" class="nav-link">← アップロード画面に戻る</a>

    @if (empty($rows))
        {{-- セッション切れや直アクセス時はアップロード画面へ誘導する --}}
        <div class="error-box">
            <ul class="error-list">
                <li>表示するデータがありません。Excelファイルを再度アップロードしてください。</li>
            </ul>
        </div>
    @else
        {{-- 送信者情報バッジ --}}
        <div class="card">
            <h2>送信者情報</h2>
            <div class="preview-meta">
                <span>送信者名：{{ $input['sender_name'] ?? '' }}</span>
                <span>会社名：{{ $input['sender_company'] ?? '' }}</span>
                <span>トーン：{{ config('mail_options.tones.' . ($input['tone'] ?? ''), $input['tone'] ?? '') }}</span>
            </div>
        </div>

        {{-- パース済み行のテーブル表示 --}}
        <div class="card">
            <h2>取り込みデータ確認</h2>
            <p class="row-count">{{ count($rows) }} 件のリードを読み込みました</p>
            <div class="preview-table-wrap">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            @php
                                // 列ヘッダーはconfig定義の日本語名を使いハードコードを避ける
                                // $columnKeysをループ外で1回だけ生成し行数分の不要な配列生成を防ぐ
                                $columns     = config('bulk_import.columns', []);
                                $columnKeys  = array_keys($columns);
                            @endphp
                            @foreach ($columns as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                @foreach ($columnKeys as $key)
                                    <td>{{ $row[$key] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="btn-row">
                <a href="{{ route('bulk') }}" class="btn btn--gray">やり直す</a>
                {{-- POSTでgenerate処理を起動しサーバー側でセッションのリードデータを参照して生成へ進む --}}
                <form method="POST" action="{{ route('bulk.generate') }}" id="generate-form">
                    @csrf
                    <button type="submit" class="btn btn--primary" id="generate-btn">一括生成する</button>
                </form>
            </div>
        </div>
    @endif

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

<script>
document.getElementById('generate-form').addEventListener('submit', function () {
    const btn = document.getElementById('generate-btn');
    btn.disabled = true;
    btn.classList.add('btn--loading');
    btn.innerHTML = '<span class="spinner"></span>生成中...';
});
</script>

</body>
</html>
