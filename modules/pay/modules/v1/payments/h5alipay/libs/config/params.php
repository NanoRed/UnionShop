<?php

return [
    'merchant' => [ // 商户号 => 渠道代号（或默认） => 配置
        '2000000000000000' => [
            'default' => [
                // 应用
                'appId' => '2019111111111111',

                // 证书路径 或 密钥
                'appCertPath' => __DIR__ . '/../certs/default/appCertPublicKey.crt',
                'alipayCertPath' => __DIR__ . '/../certs/default/alipayCertPublicKey_RSA2.crt',
                'rootCertPath' => __DIR__ . '/../certs/default/alipayRootCert.crt',
                'rsaPrivateKey' => 'AAA',

                // API请求
                'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
                'apiVersion' => '1.0',
                'postCharset' => 'UTF-8',
                'format' => 'json',
                'signType' => 'RSA2',
            ],
            'channel1' => [
                // 应用
                'appId' => '2019111111111111',

                // 证书路径 或 密钥
                'appCertPath' => __DIR__ . '/../certs/channel1/appCertPublicKey.crt',
                'alipayCertPath' => __DIR__ . '/../certs/channel1/alipayCertPublicKey_RSA2.crt',
                'rootCertPath' => __DIR__ . '/../certs/channel1/alipayRootCert.crt',
                'rsaPrivateKey' => 'AAA',

                // API请求
                'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
                'apiVersion' => '1.0',
                'postCharset' => 'UTF-8',
                'format' => 'json',
                'signType' => 'RSA2',
            ],
        ],
    ],
];