<?php

return [
    'baseUrl' => 'https://api.mch.weixin.qq.com',
    'orderApi' => '/pay/unifiedorder',
    'queryApi' => '/pay/orderquery',
    'refundApi' => '/secapi/pay/refund',
    'refundQueryApi' => '/pay/refundquery',
    'merchant' => [ // 商户号 => 渠道代号（或默认） => 配置
        '1111111111' => [
            'default' =>[
                'appId' => '123',
                'key' => 'test',
            ],
            'channel1' =>[
                'appId' => '123',
                'key' => 'test',
            ],
        ],
    ],
];