<?php

namespace app\modules\logis\modules\v1\tracks\kdniao;

use Yii;
use yii\base\Event;
use yii\db\Expression;
use app\modules\order\modules\v1\models\Order;
use app\modules\logis\modules\v1\bases\Track;
use app\modules\logis\modules\v1\models\OrderShipping;
use app\modules\logis\modules\v1\exceptions\LogisException;
use app\modules\logis\modules\v1\tracks\kdniao\libs\behaviors\Crypt;
use app\modules\logis\modules\v1\tracks\kdniao\libs\models\KdniaoCouriersMap;
use app\modules\logis\modules\v1\tracks\kdniao\libs\models\KdniaoShippingInformation;

class Main extends Track
{
    public function init()
    {
        parent::init();

        $this->params = require __DIR__ . '/libs/config/params.php';
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['crypt'] = ['class' => Crypt::className()];
        return  $behaviors;
    }

    private $credibility = 50; // Map可信积分值

    /**
     * 查找尚未进行物流跟踪的订单
     * @param $limit
     * @return array
     * @throws \Exception
     */
    public function retrieve($limit)
    {
        /*
         * 注意此查找不包括在编码映射表存在ship_way并有多条，
         * 但追踪信息表可能有编码映射表非最高可信积分的一条的那些条目
         */
        return OrderShipping::find()
            ->alias('a')
            ->select(['a.ship_no'])
            ->where([
                'AND',
                // 不需要防脏读，因为ship_sn和ship_way的更新事务为READ_COMMITTED级别
                ['!=', 'a.ship_sn', ''],
                ['!=', 'a.ship_way', ''],
                ['=', new Expression(
                    '(SELECT COUNT(*) AS num
                        FROM ' . KdniaoShippingInformation::tableName() . ' c
                        LEFT JOIN ' . KdniaoCouriersMap::tableName() . ' b ON b.courier_code = c.courier_code
                        WHERE c.shipment_number = a.ship_sn AND b.rel_courier_name = a.ship_way)'
                ), 0]
            ])
            ->orderBy('a.id')
            ->limit($limit)
            ->column();
    }

    /**
     * 识别订单并放入待订阅
     * @param mixed $shipNo
     * @return mixed|void
     * @throws LogisException
     */
    public function identify($shipNo)
    {
        // 每次循环释放各订单模型寄存器占用内存
        OrderShipping::clearRegister();
        KdniaoShippingInformation::clearRegister();

        // 对应送货单的物流单信息
        $shippingInfo = OrderShipping::findRowsByShipNo($shipNo);
        array_walk($shippingInfo, function (&$value) {
            $value = [
                'ship_no' => $value['ship_no'],
                'ship_sn' => $value['ship_sn'],
                'ship_way' => $value['ship_way'],
            ];
        });

        // 物流商编码映射地图
        $map = [];
        $temp = KdniaoCouriersMap::findRowsByRelCourierName(
            array_unique(array_column($shippingInfo, 'ship_way'))
        );
        array_multisort(
            array_column($temp,'credibility'), SORT_DESC,
            array_column($temp,'id'), SORT_DESC,
            $temp
        );
        foreach ($temp as $value) {
            $map[$value['rel_courier_name']][$value['courier_code']] = $value;
        }
        unset($temp);

        // 粒化处理，避免循环一次过多数据处理不成功回滚
        $shippingInfo = array_chunk($shippingInfo, 30);
        foreach ($shippingInfo as $chunkInfo) {
            // 使用READ_COMMITTED级别事务
            $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
            if ($task->getLevel() > 1) {
                $task->rollBack();
                throw new LogisException('禁止使用SAVEPOINT，请保证identify方法不在事务内回调');
            }
            try {
                // 检索已推入订阅表的物流单
                KdniaoShippingInformation::findRowsByShipmentNumber(
                    array_unique(array_column($chunkInfo, 'ship_sn')), false
                );

                $crd = $this->credibility; // 达到可信的积分
                $datetime = date('Y-m-d H:i:s'); // 获取当前datetime
                $identification = []; // 鉴定后的待订阅物流订单（辨别对应快递鸟物流商编码）
                $increaseCred = []; // 物流公司编码映射表中存在的条目进行可信度积分更新
                $generateCred = []; // 物流公司编码映射表中不存在的条目添加一条新的映射数据
                foreach ($chunkInfo as $value) {
                    $detail = KdniaoShippingInformation::findRowsByShipmentNumber($value['ship_sn']);
                    $detail = array_combine(array_column($detail, 'courier_code'), $detail);
                    $request = true;
                    $updateMap = false;
                    if (isset($map[$value['ship_way']])) {
                        foreach ($map[$value['ship_way']] as $key => $val) {
                            if ($map[$value['ship_way']][$key]['credibility'] >= $crd) {
                                $request = false;
                                if (!isset($detail[$key]) && !isset($identification[$key][$value['ship_sn']])) {
                                    // 新数据
                                    $identification[$key][$value['ship_sn']] = [
                                        'courier_code' => $key,
                                        'shipment_number' => $value['ship_sn'],
                                        'identification_way' => 1,
                                        'subscription_stat' => KdniaoShippingInformation::SUB_STAT_WAITING,
                                        'create_time' => $datetime
                                    ];
                                }
                                break;
                            }
                        }
                    } else {
                        $updateMap = true;
                    }
                    if ($request) {
                        // 物流商编码地图并无可信映射时请求接口获取数据
                        $reqParams = ['LogisticCode' => $value['ship_sn']];
                        $res = $this->request($reqParams, 'APIFindShipperCode');

                        $shipperName = $res['Shippers'][0]['ShipperName'];
                        $shipperCode = $res['Shippers'][0]['ShipperCode'];

                        if (!isset($detail[$shipperCode])) {
                            if (!isset($identification[$shipperCode][$value['ship_sn']])) {
                                // 新数据
                                $identification[$shipperCode][$value['ship_sn']] = [
                                    'courier_code' => $shipperCode,
                                    'shipment_number' => $value['ship_sn'],
                                    'identification_way' => 2,
                                    'identification_record' => json_encode(
                                        $res['Shippers'], JSON_UNESCAPED_UNICODE
                                    ),
                                    'subscription_stat' => KdniaoShippingInformation::SUB_STAT_WAITING,
                                    'create_time' => $datetime
                                ];

                                $updateMap = true;
                            }
                        }
                        if ($updateMap) { // 更新可信积分
                            if (isset($map[$value['ship_way']][$shipperCode])
                                && empty($map[$value['ship_way']][$shipperCode]['_is_new'])) {
                                if ($map[$value['ship_way']][$shipperCode]['credibility'] < $crd) {
                                    $map[$value['ship_way']][$shipperCode]['credibility']++;
                                    $increaseCred[$map[$value['ship_way']][$shipperCode]['id']]
                                        = $map[$value['ship_way']][$shipperCode]['credibility'];
                                }
                            } else {
                                $map[$value['ship_way']][$shipperCode]['_is_new'] = true;
                                if (isset($map[$value['ship_way']][$shipperCode]['credibility'])) {
                                    if ($map[$value['ship_way']][$shipperCode]['credibility'] < $crd) {
                                        $map[$value['ship_way']][$shipperCode]['credibility']++;
                                        $generateCred[$value['ship_way']][$shipperCode]['credibility']
                                            = $map[$value['ship_way']][$shipperCode]['credibility'];
                                    }
                                } else {
                                    $map[$value['ship_way']][$shipperCode]['credibility'] = 1;
                                    $generateCred[$value['ship_way']][$shipperCode] = [
                                        'courier_name' => $shipperName,
                                        'courier_code' => $shipperCode,
                                        'rel_courier_name' => $value['ship_way'],
                                        'credibility' => $map[$value['ship_way']][$shipperCode]['credibility'],
                                        'create_time' => $datetime
                                    ];
                                }
                            }
                        }
                    }
                }
                // 创建映射条目
                if (!empty($generateCred)) {
                    array_walk($generateCred, function (&$value) {
                        $value = array_values($value);
                    });
                    $generateCred = call_user_func_array('array_merge', $generateCred);
                    KdniaoCouriersMap::createRows($generateCred);
                }
                // 提升可信积分
                if (!empty($increaseCred)) {
                    foreach ($increaseCred as $id => $credibility) {
                        KdniaoCouriersMap::updateRowsById($id, ['credibility' => $credibility]);
                    }
                }
                // 新增订阅订单
                if (!empty($identification)) {
                    array_walk($identification, function (&$value) {
                        $value = array_values($value);
                    });
                    $identification = call_user_func_array('array_merge', $identification);
                    KdniaoShippingInformation::createRows($identification);
                }

                $task->commit();
            } catch (\Exception $e) {
                $task->rollBack();
                $errMessage = '[' . Yii::$app->getModule('channel/v1')->processor->channelAlias . ']';
                $errMessage .= $e->getMessage() . "\n";
                $errMessage .= json_encode($chunkInfo, JSON_UNESCAPED_UNICODE);
                Yii::info($errMessage, __METHOD__);
            } catch (\Throwable $e) {
                $task->rollBack();
                $errMessage = '[' . Yii::$app->getModule('channel/v1')->processor->channelAlias . ']';
                $errMessage .= $e->getMessage() . "\n";
                $errMessage .= json_encode($chunkInfo, JSON_UNESCAPED_UNICODE);
                Yii::info($errMessage, __METHOD__);
            }
        }
    }

    /**
     * 订阅订单的物流跟踪
     * @throws LogisException
     */
    public function subscribe()
    {
        $loopLimit = 100; // 限制循环次数
        while (
            $data = KdniaoShippingInformation::find()
                ->select([
                    'ShipperCode' => 'courier_code',
                    'LogisticCode' => 'shipment_number'
                ])
                ->where(['subscription_stat' => KdniaoShippingInformation::SUB_STAT_WAITING])
                ->limit(120)
                ->indexBy('id')
                ->asArray()
                ->all()
        ) {
            $loopLimit--;
            if ($loopLimit < 0) break;

            $chunks = array_chunk($data, 30, true);
            foreach ($chunks as $reqParams) {
                $timerStart = microtime(true);

                // 使用READ_COMMITTED级别事务
                $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
                if ($task->getLevel() > 1) {
                    $task->rollBack();
                    throw new LogisException('禁止使用SAVEPOINT，请保证syncStat方法不在事务内回调');
                }
                try {
                    $datetime = date('Y-m-d H:i:s');
                    $result = $this->batchRequest($reqParams, 'APISubscribeMonitor');
                    $n = 0;
                    $sucId = [];
                    foreach ($reqParams as $id => $value) {
                        if (isset($result[$n]['Success']) && $result[$n]['Success'] === true) {
                            $sucId[] = $id;
                        } else {
                            if (is_string($result[$n])) {
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $id, KdniaoShippingInformation::SUB_STAT_ERROR,
                                    [], ['subscription_err' => $result[$n], 'subscription_time' => $datetime]
                                );
                            } elseif (!empty($result[$n]['Reason'])) {
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $id, KdniaoShippingInformation::SUB_STAT_ERROR,
                                    [], ['subscription_err' => $result[$n]['Reason'], 'subscription_time' => $datetime]
                                );
                            } else {
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $id, KdniaoShippingInformation::SUB_STAT_ERROR,
                                    [], ['subscription_err' => '未知请求错误', 'subscription_time' => $datetime]
                                );
                            }
                        }
                        $n++;
                    }
                    if (!empty($sucId)) {
                        KdniaoShippingInformation::updateSubscriptionStatById(
                            $sucId, KdniaoShippingInformation::SUB_STAT_SUCCESS,
                            [], ['subscription_time' => $datetime]
                        );
                    }

                    $task->commit();
                } catch (\Exception $e) {
                    $task->rollBack();
                    Yii::info(
                        $e->getMessage() . "\n" . json_encode($reqParams, JSON_UNESCAPED_UNICODE),
                        __METHOD__
                    );
                } catch (\Throwable $e) {
                    $task->rollBack();
                    Yii::info(
                        $e->getMessage() . "\n" . json_encode($reqParams, JSON_UNESCAPED_UNICODE),
                        __METHOD__
                    );
                }

                $timerEnd = microtime(true);
                $wait = (int)(($timerEnd - $timerStart) * 1000000);
                if ($wait < 1300000) {
                    usleep((1300000 - $wait)); // 文档说明接口并发不超过30次每秒，取1.3秒请求30次
                }
            }
        }
    }

    /**
     * 接收订阅推送回调
     * @throws LogisException
     */
    public function callback()
    {
        // 当前时间
        $datetime = date('Y-m-d H:i:s');

        // 获取操作类
        $action = Yii::$app->controller->action;

        // 附加失败事件
        Event::on($action::className(), $action::EVENT_CALLBACK_FAILURE, function () use ($datetime) {
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->data = json_encode([
                'EBusinessID' => $this->params['EBusinessID'],
                'UpdateTime' => $datetime,
                'Success' => false,
                'Reason' => 'failure'
            ], JSON_UNESCAPED_UNICODE);
        });

        // 接收数据
        $data = Yii::$app->request->post();

        // 验证数据
        if (empty($data['EBusinessID']) || $data['EBusinessID'] != $this->params['EBusinessID']) {
            throw new LogisException('EBusinessID不正确');
        }

        // 处理数据
        foreach ($data['Data'] as $value) {
            if (empty($value['EBusinessID']) || $value['EBusinessID'] != $this->params['EBusinessID']) {
                throw new LogisException('Data.EBusinessID不正确');
            } elseif (empty($value['Success'])) {
                throw new LogisException(isset($value['Reason']) ? $value['Reason'] : 'Success为false');
            }
            $update = [
                'trace_call' => KdniaoShippingInformation::CALL_SUCCESS,
                'trace_state' => empty($value['State']) ? 0 : (int)$value['State'],
                'trace_state_ex' => empty($value['StateEx']) ? 0 : (int)$value['StateEx'],
                'trace_detail' => json_encode($value['Traces'], JSON_UNESCAPED_UNICODE),
                'trace_update_time' => $datetime
            ];
            $n = KdniaoShippingInformation::updateAll(
                $update, ['courier_code' => $value['ShipperCode'], 'shipment_number' => $value['LogisticCode']]
            );
            if ($n <= 0) {
                throw new LogisException("[{$value['ShipperCode']}]{$value['LogisticCode']}更新失败");
            }
        }

        // 附加成功事件
        Event::on($action::className(), $action::EVENT_CALLBACK_SUCCESS, function () use ($datetime) {
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->data = json_encode([
                'EBusinessID' => $this->params['EBusinessID'],
                'UpdateTime' => $datetime,
                'Success' => true,
                'Reason' => ''
            ], JSON_UNESCAPED_UNICODE);
        });
    }

    /**
     * 物流状态同步
     * @return mixed|void
     * @throws LogisException
     * @throws \yii\db\Exception
     */
    public function synchronize()
    {
        $loopLimit = 100; // 限制循环次数
        while (
            $data = Yii::$app->db->createCommand(
                'SELECT `order_no`, `ship_no`, `ship_stat`
                FROM (
                    SELECT
                        `a`.`order_no`,
                        `a`.`ship_no`,
                        IF(
                            `c`.`trace_state` = ' . KdniaoShippingInformation::TRACE_STAT_SIGNED . ',
                            ' . OrderShipping::SIGNED . ',
                            ' . OrderShipping::ABNORMAL . '
                        ) AS `ship_stat`,
                        `b`.`credibility`
                    FROM ' . OrderShipping::tableName() . ' `a`
                    INNER JOIN ' . KdniaoCouriersMap::tableName() . ' `b`
                        ON `b`.`rel_courier_name` = `a`.`ship_way`
                    INNER JOIN ' . KdniaoShippingInformation::tableName() . ' `c`
                        ON `c`.`courier_code` = `b`.`courier_code` 
                        AND `c`.`shipment_number` = `a`.`ship_sn`
                    WHERE 
                        `a`.`ship_stat` = ' . OrderShipping::SHIPPED . '
                        AND `a`.`locked_time` < \'' . date('Y-m-d H:i:s') . '\'
                        AND `c`.`trace_call` = ' . KdniaoShippingInformation::CALL_SUCCESS . ' 
                        AND `c`.`trace_state` IN (
                            ' . KdniaoShippingInformation::TRACE_STAT_SIGNED . ',
                            ' . KdniaoShippingInformation::TRACE_STAT_ABNORMAL . '
                        )
                    ORDER BY `credibility` DESC
                ) `t`
                GROUP BY `ship_no`
                LIMIT 100'
            )->queryAll()
        ) {
            $loopLimit--;
            if ($loopLimit < 0) break;

            $chunks = array_chunk($data, 30); // 分块事务，粒化处理，避免卡死一大片数据无法处理
            foreach ($chunks as $unit) {
                // 使用READ_COMMITTED级别事务，不允许脏读
                $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
                if ($task->getLevel() > 1) {
                    $task->rollBack();
                    throw new LogisException('禁止使用SAVEPOINT，请保证synchronize方法不在事务内回调');
                }
                try {
                    $group = [];
                    array_walk($unit, function ($value) use (&$group) {
                        $group[$value['ship_stat']][$value['order_no']] = $value['ship_no'];
                    });
                    foreach ($group as $stat => $number) {
                        $n = OrderShipping::updateStatByShipNo($number, $stat);
                        if ($n != count($number)) {
                            throw new LogisException('送货订单状态更新失败');
                        }
                        if ($stat == OrderShipping::SIGNED) {
                            // 有可能送到货前已经进行了退款操作
                            Order::updateStatByOrderNo(
                                array_keys($number), Order::COMPLETE, ['order_stat' => Order::PAID]
                            );
                        }
                    }

                    $task->commit();
                } catch (\Exception $e) {
                    $task->rollBack();
                    Yii::info($e->getMessage() . "\n" . json_encode($unit), __METHOD__);
                } catch (\Throwable $e) {
                    $task->rollBack();
                    Yii::info($e->getMessage() . "\n" . json_encode($unit), __METHOD__);
                }
            }
        }
    }

    /**
     * 查询订单物流信息
     * @param $orderNo
     * @return array
     * @throws LogisException
     */
    public function trace($orderNo)
    {
        $result = []; // 返回结果
        $orderShipping = OrderShipping::findRowsByOrderNo($orderNo);
        if (empty($orderShipping)) {
            throw new LogisException('送货订单不存在');
        } elseif (empty($orderShipping['ship_sn'])) {
            throw new LogisException('尚未产生物流订单');
        } elseif (empty($orderShipping['ship_way'])) {
            throw new LogisException('物流信息有误，请联系客服');
        } else {
            $missing = 0; // 0正常，1物流编码映射缺失，2物流跟踪信息缺失
            $record = null; // 单号识别API结果
            $courierCode = null; // 至高可信物流编码
            $isFuzzy = true; // 可用积分是否未超过可用值
            do {
                // 找出最高可信对应快递鸟物流编码
                $map = KdniaoCouriersMap::findRowsByRelCourierName($orderShipping['ship_way']);
                if (empty($map)) {
                    $missing = 1;
                    break;
                }
                array_multisort(
                    array_column($map, 'credibility'), SORT_ASC,
                    array_column($map, 'id'), SORT_ASC,
                    $map
                );
                $map = end($map);
                if ($map['credibility'] >= $this->credibility) {
                    $isFuzzy = false;
                }
                $courierCode = $map['courier_code'];

                // 查找物流信息
                $info = KdniaoShippingInformation::findRowsByShipmentNumber($orderShipping['ship_sn']);
                if (empty($info)) {
                    $missing = 2;
                    break;
                } else {
                    $data = null;
                    foreach ($info as $value) {
                        if ($value['courier_code'] == $courierCode) {
                            $data = $value;
                        }
                    }
                    if (empty($data)) {
                        $missing = 2;
                        break;
                    } elseif ($data['subscription_stat'] == KdniaoShippingInformation::SUB_STAT_WAITING) {
                        throw new LogisException('尚未产生物流信息');
                    } elseif ($data['subscription_stat'] == KdniaoShippingInformation::SUB_STAT_ERROR) {
                        // 尝试重新订阅（每天仅可尝试一次）
                        if (date('Y-m-d', strtotime($data['subscription_time'])) != date('Y-m-d')) {
                            $datetime = date('Y-m-d H:i:s');
                            try {
                                $reqParams = [
                                    'ShipperCode' => $data['courier_code'],
                                    'LogisticCode' => $data['shipment_number'],
                                ];
                                $this->request($reqParams, 'APISubscribeMonitor');
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $data['id'], KdniaoShippingInformation::SUB_STAT_SUCCESS,
                                    [], ['subscription_time' => $datetime]
                                );
                            } catch (\Exception $e) {
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $data['id'], KdniaoShippingInformation::SUB_STAT_ERROR,
                                    [], ['subscription_err' => $e->getMessage(), 'subscription_time' => $datetime]
                                );
                            } catch (\Throwable $e) {
                                KdniaoShippingInformation::updateSubscriptionStatById(
                                    $data['id'], KdniaoShippingInformation::SUB_STAT_ERROR,
                                    [], ['subscription_err' => $e->getMessage(), 'subscription_time' => $datetime]
                                );
                            }
                            Yii::info(
                                "[{$data['courier_code']}]{$data['shipment_number']}" .
                                "订阅异常，已重新订阅，若有必要请检查",
                                __METHOD__
                            );
                        }
                        throw new LogisException('物流信息订阅异常，请联系客服');
                    } elseif ($data['trace_call'] == KdniaoShippingInformation::CALL_WAITING) {
                        if ((time() - strtotime($data['subscription_time'])) > 86400 * 2 // 超过48小时没收到订阅推送的
                            && strtotime($data['probably_abnormal']) < strtotime($data['subscription_time'])) {
                            KdniaoShippingInformation::updateRowsById(
                                $data['id'], ['probably_abnormal' => date('Y-m-d H:i:s')]
                            );
                            Yii::info(
                                "[{$data['courier_code']}]{$data['shipment_number']}" .
                                "订阅成功后超过48小时没有收到订阅推送，若有必要请检查",
                                __METHOD__
                            );
                        }
                        throw new LogisException('暂无对应物流信息');
                    } elseif ($data['subscription_stat'] == KdniaoShippingInformation::SUB_STAT_SUCCESS
                        && $data['trace_call'] == KdniaoShippingInformation::CALL_SUCCESS) { // 正常的
                        $trace = json_decode($data['trace_detail'], true);
                        if (is_array($trace)) {
                            foreach ($trace as $value) {
                                $result[] = [
                                    'time' => $value['AcceptTime'],
                                    'message' => $value['AcceptStation'],
                                ];
                            }
                        }
                    } else {
                        throw new LogisException('未知错误');
                    }
                }
            } while (false); // 借用while的break跳出一段逻辑

            if ($missing > 0) { // 缺失，执行补写
                try {
                    if ($missing == 1) { // 物流编码映射缺失
                        $reqParams = ['LogisticCode' => $orderShipping['ship_sn']];
                        $res = $this->request($reqParams, 'APIFindShipperCode');
                        $record = $res['Shippers'];
                        $insertMap = [[
                            'courier_name' => $res['Shippers'][0]['ShipperName'],
                            'courier_code' => $res['Shippers'][0]['ShipperCode'],
                            'rel_courier_name' => $orderShipping['ship_way'],
                            'credibility' => 1,
                            'create_time' => date('Y-m-d H:i:s')
                        ]];
                        KdniaoCouriersMap::createRows($insertMap);
                        $courierCode = $res['Shippers'][0]['ShipperCode'];
                    }
                    $identification = [
                        'courier_code' => $courierCode,
                        'shipment_number' => $orderShipping['ship_sn'],
                        'identification_way' => $missing == 1 ?
                            KdniaoShippingInformation::WAY_REQUEST :
                            ($isFuzzy ? KdniaoShippingInformation::WAY_FUZZY : KdniaoShippingInformation::WAY_MAPPING),
                    ];
                    if ($identification['identification_way'] == KdniaoShippingInformation::WAY_REQUEST) {
                        $identification['identification_record'] =
                            empty($record) ? '' : json_encode($record, JSON_UNESCAPED_UNICODE);
                    }
                    $identification['subscription_stat'] = KdniaoShippingInformation::SUB_STAT_WAITING;
                    $identification['create_time'] = date('Y-m-d H:i:s');
                    $insertInfo = [$identification];
                    KdniaoShippingInformation::createRows($insertInfo);
                    throw new LogisException('暂无对应物流信息');
                } catch (\Exception $e) {
                    throw new LogisException('暂无对应物流信息');
                } catch (\Throwable $e) {
                    throw new LogisException('暂无对应物流信息');
                }
            }
        }

        return $result;
    }
}