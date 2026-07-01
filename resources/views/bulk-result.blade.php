<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MailFlow - 一括生成結果</title>
    <link rel="stylesheet" href="{{ asset('css/mailflow.css') }}">
</head>
<body>

<div class="header">
    <h1>MailFlow</h1>
    <span>一括生成結果</span>
</div>

<div class="container">

    <a href="{{ route('bulk') }}" class="nav-link">← アップロード画面に戻る</a>

    @if (empty($results))
        {{-- セッション切れや直アクセス時はアップロード画面へ誘導する --}}
        <div class="error-box">
            <ul class="error-list">
                <li>表示する生成結果がありません。Excelファイルを再度アップロードしてください。</li>
            </ul>
        </div>
    @else
        <div class="card">
            <h2>生成結果</h2>
            <p class="row-count">{{ count($results) }} 件のメールを生成しました</p>

            <a href="{{ route('bulk.download') }}" class="btn btn-primary">Excelダウンロード</a>

            @foreach ($results as $index => $result)
                <div class="result-item">
                    <p class="result-label"># {{ $index + 1 }}</p>

                    @if (isset($result['error']))
                        {{-- API失敗行はエラーメッセージを表示する --}}
                        <div class="error-box">
                            <ul class="error-list">
                                <li>{{ $result['error'] }}</li>
                            </ul>
                        </div>
                    @else
                        <div class="subject-box">
                            <p class="result-label">件名</p>
                            <div class="result-box">{{ $result['subject'] ?? '' }}</div>
                        </div>
                        <div>
                            <p class="result-label">本文</p>
                            <div class="result-box">{{ $result['body'] ?? '' }}</div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

</body>
</html>
