<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\GMC;

use Eccube\Common\EccubeConfig;
use Eccube\Plugin\AbstractPluginManager;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * GMC Plugin Manager
 * EC-CUBE 4.2/4.3 両対応
 */
class PluginManager extends AbstractPluginManager
{
    private const GMC_CLIENT_IDENTIFIER = 'eccube_gmc_client';

    /**
     * {@inheritdoc}
     */
    public function install(array $meta, ContainerInterface $container)
    {
        // OAuth2クライアント登録（League OAuth2 Server Bundle）
        $this->registerOAuth2Client($container);

        // PluginDataディレクトリ作成
        /** @var EccubeConfig $eccubeConfig */
        $eccubeConfig = $container->get(EccubeConfig::class);
        @mkdir($eccubeConfig->get('plugin_data_realdir').'/GMC');

        $fs = new Filesystem();
        $routeYaml = $eccubeConfig->get('plugin_data_realdir').'/GMC/routes.yaml';
        if (!$fs->exists($routeYaml)) {
            $fs->dumpFile($routeYaml, '');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $meta, ContainerInterface $container)
    {
        // OAuth2クライアントが存在しない場合は登録
        $this->registerOAuth2Client($container);

        /** @var EccubeConfig $eccubeConfig */
        $eccubeConfig = $container->get(EccubeConfig::class);

        $fs = new Filesystem();
        $routeYaml = $eccubeConfig->get('plugin_data_realdir').'/GMC/routes.yaml';
        if (!$fs->exists($routeYaml)) {
            $fs->dumpFile($routeYaml, '');
        }
    }

    /**
     * OAuth2クライアントを登録する
     * @param ContainerInterface $container
     */
    private function registerOAuth2Client(ContainerInterface $container)
    {
        try {
            /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
            $entityManager = $container->get('doctrine.orm.entity_manager');

            // 既存のクライアントがあるか確認
            $existingClient = $entityManager->getRepository(Client::class)
                ->findOneBy(['identifier' => self::GMC_CLIENT_IDENTIFIER]);

            if ($existingClient !== null) {
                return;
            }

            // 新しいクライアントを作成
            $secret = hash('sha512', random_bytes(32));
            $client = new Client('GMC Plugin', self::GMC_CLIENT_IDENTIFIER, $secret);

            // スコープ設定
            $client->setScopes(
                new Scope('read'),
                new Scope('write')
            );

            // グラント設定
            $client->setGrants(
                new Grant(OAuth2Grants::AUTHORIZATION_CODE),
                new Grant(OAuth2Grants::REFRESH_TOKEN)
            );

            // リダイレクトURI設定
            $gmcProxyUrl = env('GMC_PROXY_URL', 'https://gmc-proxy.ec-cube.net');
            $client->setRedirectUris(new RedirectUri($gmcProxyUrl.'/eccube/callback'));

            // クライアントを有効化
            $client->setActive(true);

            // 保存
            $entityManager->persist($client);
            $entityManager->flush();

            log_info('GMC Plugin: OAuth2クライアントを登録しました: '.self::GMC_CLIENT_IDENTIFIER);
        } catch (\Exception $e) {
            // API42プラグインがインストールされていない場合などはスキップ
            log_info('GMC Plugin: OAuth2クライアント登録をスキップしました: '.$e->getMessage());
        }
    }
}
