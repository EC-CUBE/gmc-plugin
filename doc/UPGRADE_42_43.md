# EC-CUBE 4.2/4.3 両対応 修正サマリー

このドキュメントはgmc-pluginをEC-CUBE 4.2および4.3に対応させた際の修正内容をまとめたものです。

---

## 対応バージョン

| 項目 | 変更前 | 変更後 |
|------|--------|--------|
| プラグインバージョン | 1.1.5 | 2.0.0 |
| 対応EC-CUBE | 4.0系 | **4.2/4.3** |
| PHP | 7.4+ | **8.1+** |

### 動作確認済み環境

| EC-CUBE | PHP | 状態 |
|---------|-----|------|
| 4.2.x | 8.1 | ✅ 動作確認済 |
| 4.3.x | 8.1+ | ✅ 動作確認済 |

---

## 主要な変更点

### 1. OAuth2ライブラリの変更

EC-CUBE 4.2/4.3ではOAuth2ライブラリが変更されています。

| バージョン | OAuth2ライブラリ |
|-----------|-----------------|
| 4.0 / 4.1 | `Trikoder\Bundle\OAuth2Bundle` |
| 4.2 / 4.3 | `League\Bundle\OAuth2ServerBundle` |

### 2. APIプラグインの名前空間

4.2/4.3共通で`Plugin\Api42`名前空間を使用します。

| 変更前 | 変更後 |
|--------|--------|
| `Plugin\Api\GraphQL\Query` | `Plugin\Api42\GraphQL\Query` |
| `Plugin\Api\GraphQL\Mutation` | `Plugin\Api42\GraphQL\Mutation` |
| `Plugin\Api\GraphQL\Types` | `Plugin\Api42\GraphQL\Types` |
| `Plugin\Api\Entity\WebHook` | `Plugin\Api42\Entity\WebHook` |
| `Plugin\Api\Repository\WebHookRepository` | `Plugin\Api42\Repository\WebHookRepository` |

### 3. ContainerInterfaceの互換性

4.2と4.3でContainerInterfaceが異なりますが、`Psr\Container\ContainerInterface`を使用することで両対応しています。

| EC-CUBE | AbstractPluginManager の ContainerInterface |
|---------|---------------------------------------------|
| 4.2 | `Symfony\Component\DependencyInjection\ContainerInterface` |
| 4.3 | `Psr\Container\ContainerInterface` |

**解決策:** `Psr\Container\ContainerInterface`を使用（Symfonyの型はPsrを継承しているため互換性あり）

---

## 修正ファイル一覧

### PluginManager.php

**修正内容:**
- `Psr\Container\ContainerInterface`を使用
- 戻り値型宣言なし（4.2互換のため）
- OAuth2クライアント登録を League OAuth2 Server Bundle で実装

```php
use Psr\Container\ContainerInterface;

// 戻り値型なし（4.2/4.3両対応）
public function install(array $meta, ContainerInterface $container)
{
    // ...
}
```

---

### services.yaml

**修正内容:**
- Trikoder関連のサービス定義を削除
- プラグインのサービス自動登録設定を追加

```yaml
parameters:
  gmc_proxy_url: 'https://gmc-proxy.ec-cube.net'

services:
  _defaults:
    autowire: true
    autoconfigure: true

  Plugin\GMC\:
    resource: '../../*'
    exclude:
      - '../../Tests/'
```

---

### GraphQL/*.php

**修正内容:**
- `Plugin\Api` → `Plugin\Api42` に変更

```php
// 変更前
use Plugin\Api\GraphQL\Query;

// 変更後
use Plugin\Api42\GraphQL\Query;
```

---

### composer.json

**修正内容:**
- バージョンを `2.0.0` に更新
- APIプラグイン依存を `^4.2 || ^4.3` に変更

```json
{
  "name": "ec-cube/gmc",
  "version": "2.0.0",
  "description": "Google Merchant Center",
  "type": "eccube-plugin",
  "require": {
    "ec-cube/plugin-installer": "^2.0",
    "ec-cube/api42": "^4.2 || ^4.3"
  },
  "extra": {
    "code": "GMC"
  }
}
```

---

### Tests/GraphQL/SiteVerifyMutationTest.php

**修正内容:**
- PHP 8対応として型宣言を追加

```php
// 変更前
public function setUp()

// 変更後
public function setUp(): void
```

---

## 依存プラグイン

GMCプラグインは以下のプラグインに依存しています。

| プラグイン | バージョン | 用途 |
|-----------|-----------|------|
| ec-cube/api42 | ^4.2 \|\| ^4.3 | GraphQL API機能、OAuth2認証 |

**インストール方法:**

```bash
# EC-CUBE 4.2/4.3共通
php bin/console eccube:composer:require ec-cube/Api42
php bin/console eccube:plugin:enable --code=Api42
```

---

## OAuth2クライアントの自動登録

プラグインインストール時に、以下のOAuth2クライアントが自動登録されます。

| 項目 | 値 |
|------|-----|
| クライアントID | `eccube_gmc_client` |
| 名前 | `GMC Plugin` |
| スコープ | `read`, `write` |
| グラント | `AUTHORIZATION_CODE`, `REFRESH_TOKEN` |
| リダイレクトURI | `https://gmc-proxy.ec-cube.net/eccube/callback` |

---

## インストール手順

### 1. 前提条件

- EC-CUBE 4.2.x または 4.3.x
- PHP 8.1以上
- Api42プラグインがインストール済み

### 2. プラグインのインストール

```bash
# GMCプラグインをapp/Plugin/GMCに配置
php bin/console eccube:plugin:install --code=GMC
php bin/console eccube:plugin:enable --code=GMC
```

### 3. 動作確認

1. 管理画面にログイン
2. `/admin/gmc/config` にアクセス
3. 「Google Merchant Center 連携設定」ボタンが表示されることを確認

---

## 環境変数

| 変数名 | デフォルト値 | 説明 |
|--------|-------------|------|
| `GMC_PROXY_URL` | `https://gmc-proxy.ec-cube.net` | GMC Proxyサービスのベースurl |

---

## テスト実行

```bash
cd /path/to/ec-cube
./vendor/bin/phpunit app/Plugin/GMC/Tests/
```

---

## 作業日

- 対応日: 2026-01-16

