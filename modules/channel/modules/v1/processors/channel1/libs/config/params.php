<?php

return [
    // 待重写系统接入参数
    'rewrite' => [
        // RSA密钥对，公钥
        'publicKey' => 'AAA',
        // RSA密钥对，私钥
        'privateKey' => 'BBB',
    ],
    // 联合登陆接入参数
    'login' => [
        // RSA密钥对1，我方持有私钥，对方持有公钥
        'privateKey' => 'AAA',
        // RSA密钥对2，我方持有公钥，对方持有私钥
        'publicKey' => 'BBB',
        // 登陆URL
        'url' => 'https://api.channel1.com/',
        // 接口代码 假设1234为登陆
        'transCode' => 1234,
        // 分配的渠道号
        'channelId' => 'SAMPLE',
    ],
    // APP唤起协议
    'appSchema' => 'channel1://',
    // 商城首页
    'homePage' => 'https://app.sample.com/home',
    // 支付引导页
    'paidGuide' => 'https://app.sample.com/paid-guide',
];