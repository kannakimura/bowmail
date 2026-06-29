# BowMail

BtoB 向けリードナーチャリングメール生成ツール。  
訪問ページ・行動フェーズを入力すると Claude AI がフォローメール文章を自動生成します。

## 概要

MAツールで「誰がどのページを見たか」は把握できても、そこからフォローメールを書くのは手作業です。  
BowMail はその空白を埋めるデモアプリです。

- **1件生成**: 会社名・訪問ページ・フェーズを入力 → 件名＋本文を即時生成
- **一括生成**: Excel（.xlsx）をアップロード → 全リードのメールを一括生成 → Excel ダウンロード

## 技術スタック

| 区分 | 技術 |
|---|---|
| バックエンド | Laravel 13 / PHP 8.3 |
| フロントエンド | Blade / バニラ CSS |
| AI | Claude API（Anthropic） |
| Excel 入出力 | maatwebsite/excel 3.1 |
| テスト | PHPUnit 12 |

## セットアップ

### 必要環境

- PHP 8.3+
- Composer
- Anthropic API キー

### 手順

```bash
git clone https://github.com/kannakimura/bowmail.git
cd bowmail

composer install

cp .env.example .env
php artisan key:generate
```

`.env` に API キーを設定します。

```env
ANTHROPIC_API_KEY=sk-ant-xxxxxxxx
```

開発サーバーを起動します。

```bash
php artisan serve
```

`http://localhost:8000` でアクセスできます。

### 一括生成を使う場合（ローカル）

10件 × API 呼び出しでタイムアウトが発生する場合、`php.ini` で実行時間を延長してください。

```ini
max_execution_time = 300
```

## Excel ファイル形式

一括生成に使う `.xlsx` ファイルの列構成は以下の通りです。

| 列 | ヘッダー名（1行目） |
|---|---|
| A | 会社名 |
| B | メールアドレス |
| C | 訪問ページ |
| D | フェーズ |

## テスト

```bash
php artisan test
```

60 テスト、すべて通過することを確認しています。

## ディレクトリ構成（主要部分）

```
app/
  Http/Controllers/     # MailGeneratorController, BulkMailController
  Requests/             # FormRequest（バリデーション）
  Services/             # GenerateMailService, BulkImportService, BulkGenerateService, BulkExportService
  Imports/              # LeadImport（maatwebsite/excel）
  Exports/              # LeadResultExport（maatwebsite/excel）
  Exceptions/           # InvalidColumnException, EmptyRowsException, TooManyRowsException
config/
  bulk_import.php       # 入力列定義・件数上限
  bulk_export.php       # 出力列定義
  mail_options.php      # トーン選択肢
tests/
  Unit/                 # Service 単体テスト
  Feature/              # HTTP リクエスト〜レスポンス結合テスト
  fixtures/             # テスト用 Excel ファイル
```

## ライセンス

MIT
