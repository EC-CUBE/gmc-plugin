# GMC Plugin（Google Merchant Center連携プラグイン）仕様書

## 基本情報

| 項目 | 内容 |
|------|------|
| **プラグイン名** | Google Merchant Center 連携プラグイン |
| **コード** | `GMC` |
| **バージョン** | 1.1.5（2021年9月14日） |
| **対応EC-CUBE** | 4.0系（現状） |
| **ライセンス** | LGPL-2.1 |
| **リポジトリ** | https://github.com/EC-CUBE/gmc-plugin.git |

---

## プラグインの目的

Google Merchant Center（GMC）と EC-CUBE を連携するための**中継サービス**に接続するプラグインです。

### 主な機能

- ショップ情報・商品情報をGMCにリアルタイム連携
- GMCに登録された情報はGoogleショッピング広告に出稿可能
- 集客UP施策として利用可能

---

## ファイル構成

```
gmc-plugin/
├── composer.json                    # プラグインメタ情報・依存関係
├── PluginManager.php                # インストール/アップデート処理
├── Nav.php                          # 管理画面ナビゲーション（現在空）
├── TwigBlock.php                    # Twigブロック拡張（現在空）
│
├── Controller/
│   ├── Admin/
│   │   └── ConfigController.php     # 管理画面設定コントローラー
│   └── SiteVerificationController.php  # Google サイト検証用
│
├── GraphQL/
│   ├── SiteVerifyMutation.php       # サイト検証トークン保存Mutation
│   ├── WebHookMutation.php          # WebHook設定保存Mutation
│   └── DeliveryQuery.php            # 配送方法取得Query
│
├── Resource/
│   ├── config/
│   │   ├── services.yaml            # サービス定義
│   │   └── routes.yaml              # ルーティング定義
│   ├── locale/
│   │   ├── messages.ja.yaml         # 日本語翻訳
│   │   └── validators.ja.yaml       # バリデーション翻訳
│   └── template/admin/
│       └── config.twig              # 管理画面テンプレート
│
├── doc/                             # ドキュメント
│
└── Tests/
    ├── bootstrap.php
    └── GraphQL/
        └── SiteVerifyMutationTest.php
```

---

## 依存関係

### composer.json

```json
{
  "name": "ec-cube/gmc",
  "version": "1.1.5",
  "description": "Google Merchant Center",
  "type": "eccube-plugin",
  "require": {
    "ec-cube/plugin-installer": "~0.0.7 || ^2.0",
    "ec-cube/api": "~1.0 || ~2.1"
  },
  "extra": {
    "code": "GMC"
  }
}
```

### 重要な依存

| パッケージ | 用途 |
|-----------|------|
| `ec-cube/api` | EC-CUBE APIプラグイン（GraphQL機能を使用） |
| `Trikoder\Bundle\OAuth2Bundle` | OAuth2認証（APIプラグイン経由） |

---

## 主要機能の詳細

### 1. OAuth2クライアント登録（インストール時）

`PluginManager.php` にて、プラグインインストール時にOAuth2クライアントを登録します。

| 項目 | 値 |
|------|-----|
| クライアントID | `eccube_gmc_client` |
| スコープ | `read`, `write` |
| グラント | `AUTHORIZATION_CODE`, `REFRESH_TOKEN` |
| リダイレクトURI | `https://gmc-proxy.ec-cube.net/eccube/callback` |

#### 処理内容

```php
public function install(array $meta, ContainerInterface $container)
{
    $clientManager = $container->get(ClientManagerInterface::class);

    if ($clientManager->find('eccube_gmc_client') === null) {
        $Client = new Client('eccube_gmc_client', hash('sha512', random_bytes(32)));
        $Client->setScopes(
            new Scope('read'),
            new Scope('write'));
        $Client->setGrants(
            new Grant(OAuth2Grants::AUTHORIZATION_CODE),
            new Grant(OAuth2Grants::REFRESH_TOKEN));
        $Client->setRedirectUris(new RedirectUri(env('GMC_PROXY_URL', 'https://gmc-proxy.ec-cube.net').'/eccube/callback'));
        $clientManager->save($Client);
    }

    // PluginDataディレクトリ作成
    @mkdir($eccubeConfig->get('plugin_data_realdir').'/GMC');

    // 空のルートファイル作成
    $fs->dumpFile($routeYaml, '');
}
```

---

### 2. 管理画面設定

| 項目 | 値 |
|------|-----|
| ルート | `/admin/gmc/config` |
| コントローラー | `ConfigController` |
| テンプレート | `@GMC/admin/config.twig` |

GMC Proxyサービスへのリンクを表示し、OAuth認証フローを開始します。

#### テンプレート内容

```twig
<a class="btn btn-primary" target="_blank" 
   href="{{ gmc_proxy_url }}?authorize_url={{ url('oauth2_authorize') }}&top_url={{ url('homepage') }}&shop_name={{ BaseInfo.shop_name }}">
   Google Merchant Center 連携設定
</a>
```

---

### 3. Google サイト検証機能

GMCとの連携にはGoogleによるサイト所有権の確認が必要です。

#### フロー

1. GMC Proxyから GraphQL Mutation `gmc_save_verification_token` が呼ばれる
2. 検証用ファイル `google-site-verification.txt` を `PluginData/GMC/` に保存
3. 動的ルーティングを `routes.yaml` に追加
4. Googleのクローラーが `/{token}` にアクセスして所有権確認

#### 処理内容

```php
public function saveToken($root, $args)
{
    $content = 'google-site-verification: '.$args['token'];

    $fs = new Filesystem();
    // 検証ファイル保存
    $fs->dumpFile(
        $this->eccubeConfig['plugin_data_realdir'].'/GMC/google-site-verification.txt',
        $content
    );

    // 動的ルーティング作成
    $yaml = Yaml::dump([
        'gmc_site_verification' => [
            'path' => '/'.$args['token'],
            'controller' => 'Plugin\GMC\Controller\SiteVerificationController::verifyUrl',
        ]
    ]);
    $fs->dumpFile(
        $this->eccubeConfig['plugin_data_realdir'].'/GMC/routes.yaml',
        $yaml);

    // ルーティングキャッシュをクリア
    $this->clearRouting();

    return $content;
}
```

---

### 4. WebHook設定

GMC Proxyからの通知を受けるためのWebHook設定を行います。

| GraphQL Mutation | `gmc_save_webhook` |
|------------------|-------------------|
| 引数 | `secret: String!` |
| PayloadURL | `{GMC_PROXY_URL}/eccube/webhook` |

#### 処理内容

```php
public function saveWebHook($root, $args)
{
    $payloadUrl = env('GMC_PROXY_URL', $this->eccubeConfig['gmc_proxy_url']).'/eccube/webhook';
    $WebHook = $this->webHookRepository->findOneBy(['payloadUrl' => $payloadUrl]);
    
    if (!$WebHook) {
        $WebHook = new WebHook();
        $WebHook->setPayloadUrl($payloadUrl);
        $WebHook->setEnabled(true);
    }
    $WebHook->setSecret($args['secret']);
    $this->webHookRepository->save($WebHook);
    $this->entityManager->flush();

    return $WebHook->getSecret();
}
```

---

### 5. 配送方法取得API

GMC Proxyが商品の配送料金を取得するためのGraphQL Query。

| GraphQL Query | `gmc_deliveries` |
|---------------|------------------|
| 戻り値 | `[Delivery]` |

#### 処理内容

```php
public function getQuery()
{
    return [
        'type' => Type::listOf($this->types->get(Delivery::class)),
        'resolve' => function ($root, $args) {
            return $this->deliveryRepository->createQueryBuilder('d')
                ->addSelect('df', 'p')
                ->innerJoin('d.DeliveryFees', 'df')
                ->innerJoin('df.Pref', 'p')
                ->getQuery()->getResult();
        },
    ];
}
```

---

## 連携アーキテクチャ

```
┌─────────────────┐          ┌──────────────────────┐          ┌───────────────────┐
│   EC-CUBE       │◀────────▶│   GMC Proxy          │◀────────▶│  Google Merchant  │
│  + GMC Plugin   │  OAuth2  │ gmc-proxy.ec-cube.net│   API    │     Center        │
└─────────────────┘  GraphQL └──────────────────────┘          └───────────────────┘
```

### データフロー

1. 管理者が管理画面から「連携設定」ボタンをクリック
2. GMC Proxyへリダイレクト、OAuth2認証
3. GMC ProxyがEC-CUBEのAPIを通じて商品情報を取得
4. 商品更新時、WebHook経由でGMC Proxyへ通知
5. GMC ProxyがGoogleに商品情報を同期

---

## GraphQL API一覧

| 種別 | 名前 | 用途 |
|------|------|------|
| Mutation | `gmc_save_verification_token` | サイト検証トークン保存 |
| Mutation | `gmc_save_webhook` | WebHook設定保存 |
| Query | `gmc_deliveries` | 配送方法一覧取得 |

---

## 環境変数

| 変数名 | デフォルト値 | 説明 |
|--------|-------------|------|
| `GMC_PROXY_URL` | `https://gmc-proxy.ec-cube.net` | GMC Proxyサービスのベースurl |

---

## PluginDataディレクトリ構成

プラグインは以下のファイルを `app/PluginData/GMC/` に保存します。

| ファイル | 用途 |
|----------|------|
| `routes.yaml` | サイト検証用の動的ルーティング定義 |
| `google-site-verification.txt` | Google検証用トークンファイル |

---

## キャッシュクリア処理

サイト検証トークン保存時、ルーティングキャッシュをクリアします。

### 対象ファイル

**4.1系:**
- `{cache_dir}/UrlGenerator.php`
- `{cache_dir}/UrlGenerator.php.meta`
- `{cache_dir}/UrlMatcher.php`
- `{cache_dir}/UrlMatcher.php.meta`

**4.0系:**
- `{cache_dir}/EccubeProdProjectContainerUrlGenerator.php`
- `{cache_dir}/EccubeProdProjectContainerUrlGenerator.php.meta`
- `{cache_dir}/EccubeProdProjectContainerUrlMatcher.php`
- `{cache_dir}/EccubeProdProjectContainerUrlMatcher.php.meta`

