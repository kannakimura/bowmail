<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BowMail - アップロード内容の確認</title>
    <link rel="stylesheet" href="{{ asset('css/bowmail.css') }}">
</head>
<body>

<div class="header">
    <h1>BowMail</h1>
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
            <p class="row-count">{{ count($rows) }} 件</p>
            <div class="preview-table-wrap">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            @php
                                // 列ヘッダーはconfig定義の日本語名を使いハードコードを避ける
                                $columns = config('bulk_import.columns', []);
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
                                @foreach (array_keys($columns) as $key)
                                    <td>{{ $row[$key] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="btn-row">
                <a href="{{ route('bulk') }}" class="btn btn--gray">やり直す</a>
                {{-- Phase 1-5-5以降で一括生成ボタンを追加する --}}
            </div>
        </div>
    @endif

</div>

<div class="footer">BowMail — Powered by Claude AI</div>

</body>
</html>
