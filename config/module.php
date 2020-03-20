<?php

return function (&$config) {
    $modules = [
        'admin', 'channel', 'item', 'login',  'logis',
        'market', 'order', 'pay', 'rewrite', 'user',
    ];
    switch ($config['id']) {
        case 'UnionSystem-web':
            $date = date('Ymd');
            $assign = function (&$config) use ($date) {
                // 设置日志组件
                $config['components']['log']['targets'] = [
                    [
                        'class' => 'yii\log\FileTarget',
                        'levels' => ['error', 'warning'],
                        'except' => ['app\modules\*'], // 排除子项
                        'logFile' => '@runtime/logs/' . $date . '/web.error.log',
                    ]
                ];
                // 关闭默认layout
                $config['layout'] = false;
            };
            $append = function (&$config, $module) use ($date) {
                switch ($module) {
                    case 'logis':
                        // 物流跟踪订阅推送日志
                        array_push(
                            $config['components']['log']['targets'],
                            [
                                'class' => 'yii\log\FileTarget',
                                'levels' => ['info'],
                                'categories' => ['app\modules\logis\modules\v1\actions\TrackAction::run'],
                                'logVars' => [],
                                'logFile' => '@runtime/logs/' . $date . '/logis.callback.track.log',
                            ]
                        );
                        break;
                    case 'order':
                        // 订单模块通用队列
                        $config['bootstrap'][] = 'queueOrder';
                        $config['components']['queueOrder'] = [
                            'class' => 'yii\queue\redis\Queue',
                            'channel' => $config['id'] . 'QueueOrder',
                            'as log' => 'yii\queue\LogBehavior'
                        ];
                        break;
                    case 'pay':
                        // 支付通知日志
                        array_push(
                            $config['components']['log']['targets'],
                            [
                                'class' => 'yii\log\FileTarget',
                                'levels' => ['info'],
                                'categories' => ['app\modules\pay\modules\v1\actions\PaymentAction::run'],
                                'logVars' => [],
                                'logFile' => '@runtime/logs/' . $date . '/pay.callback.payment.log',
                            ]
                        );
                        break;
                }
                // 添加模块
                $config['modules'][$module] = ['class' => 'app\modules\\' . $module . '\Module'];
                // 设置模块日志
                $config['components']['log']['targets'][] = [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'categories' => ['app\modules\\' . $module . '\*'],
                    'logFile' => '@runtime/logs/' . $date . '/web.' . $module . '.error.log',
                ];
            };
            $assign($config);
            foreach ($modules as $module) {
                $append($config, $module);
            }
            break;
        case 'UnionSystem-console':
            $date = date('Ymd');
            $assign = function (&$config) use ($date) {
                // 设置日志组件
                $config['components']['log']['targets'] = [
                    [
                        'class' => 'yii\log\FileTarget',
                        'levels' => ['error', 'warning'],
                        'except' => ['app\modules\*'], // 排除子项
                        'logVars' => [],
                        'logFile' => '@runtime/logs/' . $date . '/console.error.log',
                    ]
                ];
                // 设置fixture伪数据工具（测试夹具）
                $config['controllerMap']['fixture'] = [
                    'class' => 'yii\faker\FixtureController',
                ];
                // 设置迁移工具
                $config['controllerMap']['migrate'] = [
                    'class' => 'yii\console\controllers\MigrateController',
                    'migrationPath' => ['@app/migrations']
                ];
            };
            $append = function (&$config, $module) use ($date) {
                switch ($module) {
                    case 'admin':
                        // rbac
                        $config['components']['authManager'] = [
                            'class' => 'app\modules\admin\modules\v1\rbac\DbManager'
                        ];
                        // rbac和yii2-admin的迁移设置
                        array_push(
                            $config['controllerMap']['migrate']['migrationPath'],
                            '@yii/rbac/migrations', // rbac
                            '@vendor/mdmsoft/yii2-admin/migrations' // yii2-admin
                        );
                        $config['controllerMap']['migrate']['on beforeAction'] = function () {
                            Yii::$container->set('mdm\admin\components\Configs',[ // yii2-admin
                                'menuTable' => '{{%auth_menu}}',
                                'userTable' => '{{%auth_user}}',
                            ]);
                        };
                        break;
                    case 'logis':
                        // ERP日志和物流追踪日志
                        array_push(
                            $config['components']['log']['targets'],
                            [
                                'class' => 'yii\log\FileTarget',
                                'levels' => ['info'],
                                'categories' => [
                                    'app\modules\logis\modules\v1\erps\*',
                                    'app\modules\logis\modules\v1\commands\ErpController*'
                                ],
                                'logVars' => [],
                                'logFile' => '@runtime/logs/' . $date . '/' . $module . '.erp.log',
                            ],
                            [
                                'class' => 'yii\log\FileTarget',
                                'levels' => ['info'],
                                'categories' => [
                                    'app\modules\logis\modules\v1\tracks\*',
                                    'app\modules\logis\modules\v1\commands\TrackController*'
                                ],
                                'logVars' => [],
                                'logFile' => '@runtime/logs/' . $date . '/' . $module . '.track.log',
                            ]
                        );
                        break;
                    case 'order':
                        // 订单模块通用队列
                        $config['bootstrap'][] = 'queueOrder';
                        $config['components']['queueOrder'] = [
                            'class' => 'yii\queue\redis\Queue',
                            'channel' => $config['id'] . 'QueueOrder',
                            'as log' => 'yii\queue\LogBehavior'
                        ];
                        break;
                }
                // 添加模块
                $config['modules'][$module] = ['class' => 'app\modules\\' . $module . '\Module'];
                // 设置模块日志
                $config['components']['log']['targets'][] = [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'categories' => ['app\modules\\' . $module . '\*'],
                    'logVars' => [],
                    'logFile' => '@runtime/logs/' . $date . '/console.' . $module . '.error.log',
                ];
                // 设置迁移路径
                $migrationPath = '@app/modules/' . $module . '/modules/v1/migrations';
                $config['controllerMap']['migrate']['migrationPath'][] = $migrationPath;
            };
            $assign($config);
            foreach ($modules as $module) {
                $append($config, $module);
            }
            break;
    }
};