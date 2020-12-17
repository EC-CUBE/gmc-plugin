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

namespace Plugin\GMC\GraphQL;


use Eccube\Common\EccubeConfig;
use GraphQL\Type\Definition\Type;
use Plugin\Api\GraphQL\Mutation;

class SiteVerifyMutation implements Mutation
{
    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    public function getName()
    {
        return 'gmc_save_verification_token';
    }

    public function getMutation()
    {
        return [
            'type' => Type::string(),
            'args' => [
                'token' => [
                    'type' => Type::nonNull(Type::string())
                ]
            ],
            'resolve' => [$this, 'saveToken']
        ];
    }

    public function saveToken($root, $args)
    {
        $fileName = $args['token'];
        $content = 'google-site-verification: '.$fileName;
        file_put_contents($this->eccubeConfig['plugin_data_realdir']."/GMC/".$fileName, $content);
        if (is_dir($this->eccubeConfig['plugin_data_realdir']."/SiteKit")) {
            file_put_contents($this->eccubeConfig['plugin_data_realdir']."/SiteKit/google-site-verification.txt", $content);
        }
        return $content;
    }
}