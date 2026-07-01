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

            <div class="preview-table-wrap">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>会社名</th>
                            <th>件名（クリックで展開）</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($results as $index => $result)
                        @if (isset($result['error']))
                            <tr class="result-row result-row--error" onclick="toggleRow(this)">
                                <td>{{ $index + 1 }}</td>
                                <td>—</td>
                                <td class="result-row__subject">⚠ 生成エラー <span class="accordion-arrow">▼</span></td>
                            </tr>
                            <tr class="result-detail" hidden>
                                <td colspan="3">
                                    <div class="error-box"><ul class="error-list"><li>{{ $result['error'] }}</li></ul></div>
                                </td>
                            </tr>
                        @else
                            @php
                                $subject     = $result['subject'] ?? '';
                                $body        = $result['body'] ?? '';
                                $companyName = $result['company_name'] ?? '';
                                $preview     = mb_strimwidth($subject, 0, 50, '…');
                            @endphp
                            <tr class="result-row" onclick="toggleRow(this)">
                                <td>{{ $index + 1 }}</td>
                                <td class="result-row__company">{{ $companyName }}</td>
                                <td class="result-row__subject">{{ $preview }} <span class="accordion-arrow">▼</span></td>
                            </tr>
                            <tr class="result-detail" hidden>
                                <td colspan="3">
                                    <div class="subject-box">
                                        <p class="result-label">件名</p>
                                        <div class="result-box">{{ $subject }}</div>
                                    </div>
                                    <div>
                                        <p class="result-label">本文</p>
                                        <div class="result-box">{{ $body }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>

<div class="footer">MailFlow — Powered by Claude AI</div>

<script>
function toggleRow(row) {
    const detail = row.nextElementSibling;
    const arrow  = row.querySelector('.accordion-arrow');
    const isOpen = !detail.hidden;
    detail.hidden = isOpen;
    arrow.textContent = isOpen ? '▼' : '▲';
}
</script>

</body>
</html>
