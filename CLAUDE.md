# BowMail — Claude向け開発方針

## プロジェクト概要

- **名称**: BowMail（リードナーチャリングメール生成ツール）
- **スタック**: Laravel 13 / Blade / Claude API（Anthropic）
- **目的**: BowNow連携を想定したBtoB向けメール文章自動生成ポートフォリオ

---

## 開発スタイルルール

- ソースコードのコメントは**必ず日本語**で書く
- コミット粒度は**1変更1コミット**を徹底する（ファイル1つの修正でもコミットを積む）
- 実装コミットとテストコミットは必ず分ける（まとめてコミットしない）
- コミット後は都度 `git push` する（ローカルにためない）
- PRも機能単位で細かく分ける（複数機能を1PRにまとめない）
- 人間がコミット履歴を読んで変更の経緯を追えることを最優先にする
- **テストコードは実装とセットで必ず書く**
- テストなしで完了報告しない

---

## Laravelアーキテクチャ方針

あなたはLaravelに精通したシニアバックエンドエンジニアです。  
MVC構造を崩さず、長期運用・保守性・安全性を重視して実装してください。

### Controller の責務

**やること**:
- Request を受け取る
- FormRequest によるバリデーションを使う
- 認可チェックを行う
- Service / UseCase を呼び出す
- 結果を View / Redirect / JSON に変換する

**書いてはいけないこと**:
- 複雑な業務ロジック
- 複数テーブルをまたぐDB更新処理
- 外部API連携の詳細
- メール送信・通知処理の詳細
- 長い if / switch
- SQL / QueryBuilder の複雑な組み立て

**Controller は薄くする。**

---

### Model / Eloquent の責務

**やること**:
- リレーション定義 / Casts / Scope / Attribute
- DBカラムに近い単純な状態判定

**書きすぎてはいけないこと**:
- 複数Modelをまたぐ業務フロー
- 外部API呼び出し
- メール送信
- 画面専用の整形処理
- ユースケース全体の進行管理

**Model は「賢すぎる神クラス」にしない。**

---

### View / Blade の責務

**やること**:
- 値の表示 / 単純な条件分岐 / コンポーネント呼び出し

**書いてはいけないこと**:
- DBアクセス
- 複雑な業務判定
- 権限判定の本体
- 長い条件分岐 / 計算ロジック

画面表示用の整形が必要な場合は ViewModel / Presenter / Resource / DTO を検討する。

---

### Service / UseCase の使い方

Controller に業務ロジックを書かないため、必要に応じて Service または UseCase クラスを作成する。

**担当すること**:
- 業務フローの実行
- 複数Modelをまたぐ処理
- DBトランザクション
- Repository 呼び出し
- 外部APIクライアント呼び出し
- Event / Job / Notification の発火

**1クラス1目的**を原則とし、何でも入る曖昧なクラスは避ける。

```
// 悪い例
UserService { createUser() updateUser() sendMail() exportCsv() ... }

// 良い例
CreateUserService
UpdateUserService
DeleteUserService
```

---

### FormRequest

バリデーションは Controller に直接書かず、FormRequest に分離する。

```php
// Controller では以下のみ
$data = $request->validated();
```

---

### DBトランザクション

複数テーブルを更新する処理は必ずトランザクションを検討する。  
Service / UseCase 内で `DB::transaction()` を使う。

---

### Repository の扱い

**使うべきケース**:
- 複雑な検索条件がある
- 同じ検索処理を複数箇所で使う
- DB取得処理をテストで差し替えたい

**不要なケース**:
- 単純な `User::find($id)` など

---

### 認可

権限チェックは Policy / Gate / Middleware / FormRequest の authorize に集約する。  
Controller や View に直接ロール判定を書かない。

```php
// 悪い例
if ($user->role === 'admin' || $user->role === 'owner') { ... }

// 良い例
$this->authorize('update', $user);
```

---

### 例外処理

例外を握りつぶさない。

```php
// 悪い例（やってはいけない）
try { ... } catch (\Exception $e) { return false; }
```

- 想定内エラーは専用例外や Result オブジェクトで扱う
- 想定外エラーはログに残す
- ユーザーには安全なメッセージだけ返す
- 例外ログに機密情報を含めない

---

### 監査ログ

重要操作（ログイン・権限変更・支払い状態変更など）では成功時・失敗時ともにログを検討する。  
**ログに含めないもの**: パスワード / トークン / APIキー / クレジットカード情報 / 個人情報の過剰な全文

---

### 命名規則

曖昧な名前を避ける。何をする処理か名前で分かるようにする。

```
// 悪い例: handle() process() execute() manage() data() result()

// 良い例
CreateCompanyService
CancelSubscriptionUseCase
RecordPaymentFailureService
```

---

### ディレクトリ構成の方針

```
app/
  Http/
    Controllers/
    Requests/      ← FormRequest
    Middleware/
  Models/
  Policies/
  Services/
  UseCases/
  Repositories/
  DTOs/
  Events/
  Listeners/
  Jobs/
  Notifications/
```

最初から過剰に分割しない。小さい機能では Laravel 標準構成を優先し、複雑になった段階で追加する。

---

### DBアクセス・SQLの方針

DBアクセスは原則として **Eloquent ORM または Query Builder** を使用する。  
生SQLは可読性・保守性・SQLインジェクションリスク・DB差し替え耐性の観点で危険になりやすいため、安易に使用しない。

**優先順位**:
1. Eloquent ORM
2. Query Builder
3. やむを得ない場合のみ Raw SQL

```php
// 悪い例（SQLインジェクション危険・ORM恩恵なし）
DB::select("SELECT * FROM users WHERE email = '$email'");

// 良い例
User::where('email', $email)->first();

// 複雑な検索も ORM で表現する
User::query()
    ->where('company_id', $companyId)
    ->where('is_active', true)
    ->whereNull('deleted_at')
    ->orderByDesc('created_at')
    ->paginate(20);
```

**Raw SQL を使う場合は必ず以下を満たすこと**:
- Eloquent / Query Builder では表現が難しい明確な理由がある
- パラメータバインディングを使う（ユーザー入力を文字列連結しない）
- 対象箇所に理由をコメントで残す
- テストを追加する

```php
// 許容例（バインディング使用）
DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

通常のCRUD・検索・更新では Raw SQL を使わず、ORM を優先する。

---

## 実装前チェックリスト

1. この処理の入口はどの Controller か
2. バリデーションはどの FormRequest に置くか
3. 認可は Policy / Gate / Middleware のどこで行うか
4. 業務ロジックは Service / UseCase に分離すべきか
5. DB更新はトランザクションが必要か
6. 失敗時にログを残すべきか
7. テストしやすい構造になっているか
8. 将来仕様変更が来たとき、修正箇所が局所化されるか

## 実装後セルフレビュー

- [ ] Controller が薄いか
- [ ] Model が肥大化していないか
- [ ] View に業務ロジックが入っていないか
- [ ] バリデーションが FormRequest に分離されているか
- [ ] 認可が Policy / Gate 等に分離されているか
- [ ] 複数テーブル更新にトランザクションがあるか
- [ ] 例外が握りつぶされていないか
- [ ] 機密情報をログ出力していないか
- [ ] クラス名・メソッド名が具体的か
- [ ] テストしやすい構造か

---

## コーディング安全規則

コードレビューで繰り返し指摘された頻度の高いルール。実装前に必ず確認すること。

### 1. `config()` には必ずデフォルト値を指定する

```php
// 悪い例：未定義時にnullになりTypeError/配列アクセスエラーが発生する
config('mail_options.tones')[$key]

// 良い例：デフォルト[]を指定して安全にアクセスする
config('mail_options.tones', [])[$key] ?? $fallback
```

- デフォルトは `[]`（配列期待）または `''`（文字列期待）を用途に合わせて使う
- デフォルトを空文字にすると `str_contains($str, '')` が常に `true` になるため、検証用途では実際の値をデフォルトにする

### 2. FormRequest で `Rule::in()` を使う場合は前に `string` を追加する

```php
// 悪い例：配列を送信されると後続のRule::in/Serviceでエラーになる
'tone' => ['required', Rule::in([...])],

// 良い例：string制約でスカラー型を強制する
'tone' => ['required', 'string', Rule::in([...])],
```

- `visited_page` / `phase` / `tone` など選択肢系フィールドはすべてこの形式に統一する

### 3. Blade のループ内で `config()` を呼ばない

```blade
{{-- 悪い例：ループ内で毎回config()を評価する --}}
@foreach(config('mail_options.tones', []) as $value => $label)
    <option {{ old('tone', array_key_first(config('mail_options.tones', []))) === $value ...}}>

{{-- 良い例：ループ外の@phpブロックで一度だけ変数化する --}}
@php
    $tones       = config('mail_options.tones', []);
    $defaultTone = isset($tones['polite']) ? 'polite' : (array_key_first($tones) ?? '');
@endphp
@foreach($tones as $value => $label)
    <option {{ old('tone', $defaultTone) === $value ...}}>
```

---

## 最終方針

短期的に動くコードよりも、**半年後・1年後に安全に変更できるコード**を優先する。

- Controller は薄く
- Model はDB表現に寄せる
- View は表示に専念
- 業務ロジックは Service / UseCase へ
- 入力検証は FormRequest へ
- 認可は Policy / Gate へ
- 複数DB更新は Transaction へ
- 重要操作は監査ログを検討
- 例外は握りつぶさない
- 機密情報をログに出さない
- 将来の仕様変更に強い構造にする
