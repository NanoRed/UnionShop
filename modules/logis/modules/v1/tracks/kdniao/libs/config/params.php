<?php

return [
    'EBusinessID' => 'test1234567',
    'APIKey' => '12345678-12ab-ab12-abcd-123456789abc',
    'APIFindShipperCode' => [ // http://www.kdniao.com/api-recognise 单号识别API
        'Url' => 'http://sandboxapi.kdniao.com:8080/kdniaosandbox/gateway/exterfaceInvoke.json',
        'RequestType' => '2002'
    ],
    'APISubscribeMonitor' => [ // http://www.kdniao.com/api-monitor 在途监控API（订阅）
        'Url' => 'http://sandboxapi.kdniao.com:8080/kdniaosandbox/gateway/exterfaceInvoke.json',
        'RequestType' => '1008'
    ]
];