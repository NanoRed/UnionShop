<?php

namespace app\modules\logis\modules\v1\erps\wangdiantong;

use Yii;
use yii\db\Expression;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\pay\modules\v1\models\Refund;
use app\modules\pay\modules\v1\models\RefundRelation;
use app\modules\logis\modules\v1\models\OrderShipping;
use app\modules\user\modules\v1\models\District;
use app\modules\logis\modules\v1\bases\Erp;
use app\modules\logis\modules\v1\erps\wangdiantong\libs\behaviors\Crypt;
use app\modules\logis\modules\v1\erps\wangdiantong\libs\models\WangdiantongPushedOrder;
use app\modules\logis\modules\v1\exceptions\LogisException;

/**
 * 旺店通ERP
 * Class Main
 * @package app\modules\logis\modules\v1\erps\wangdiantong
 */
class Main extends Erp
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

    /**
     * 查询符合条件推送至旺店通的订单orderNo
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function retrieve($limit)
    {
        // 订单生成和支付回调的READ_UNCOMMITTED级别事务涉及OrderShipping表的修改，故使用locked_time条件
        return OrderShipping::find()
            ->alias('a')
            ->select(['a.order_no'])
            ->where([
                'AND',
                ['>=', 'a.ship_stat', OrderShipping::TO_BE_SHIPPED],
                ['<', 'locked_time', date('Y-m-d H:i:s')], // 防脏读
                ['=', new Expression(
                    '(SELECT COUNT(*) AS num
                    FROM ' . WangdiantongPushedOrder::tableName(). ' b
                    WHERE b.wdt_tid = a.order_no)'
                ), 0]
            ])
            ->orderBy('a.id')
            ->limit($limit)
            ->column();
    }

    /**
     * 发货
     * 推送ERP时当作已完成聚合系统的发货
     * 调用时注意区分渠道
     * @param mixed $orderNo
     * @return mixed|void
     * @throws LogisException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     * @throws \app\modules\pay\modules\v1\exceptions\PayException
     */
    public function dispatch($orderNo)
    {
        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 每次循环释放各订单模型寄存器占用内存
        Order::clearRegister();
        OrderItem::clearRegister();
        OrderShipping::clearRegister();
        Transfer::clearRegister();
        Refund::clearRegister();

        // 订单数据
        $orders = Order::findRowsByOrderNo($orderNo); // 主订单
        if (empty($orders)) {
            throw new LogisException('订单不存在，请检查对应渠道');
        }
        OrderItem::findRowsByOrderNo($orderNo);       // 订单商品详情
        Transfer::findRowsByOrderNo($orderNo);        // 支付订单详情
        OrderShipping::findRowsByOrderNo($orderNo);   // 订单配送详情

        // 获取退款详情
        $relations = RefundRelation::findRelationByOrderId(
            array_column($orders, 'id'), ['refund_id', 'order_id', 'order_item_id']
        );
        $relRefundIds = $relOrderIds = $relOrderItemIds = [];
        foreach ($relations as $i => $value) {
            $relRefundIds[$value['refund_id']][] = $i;
            $relOrderIds[$value['order_id']][] = $i;
            $relOrderItemIds[$value['order_item_id']][] = $i;
        }
        ksort($relRefundIds);
        $refundIds = array_keys($relRefundIds);
        $refunds = Refund::findRowsByRefundId($refundIds);
        $refunds = array_combine($refundIds, $refunds);

        // 获取同次支付的商品按权单价
        $orderItemsWeightedPrice = function ($xferNo) {

            static $price; // 静态缓存

            if (!isset($price[$xferNo])) {
                $orderNo = Transfer::findRelatedOrderNoByXferNo($xferNo);
                $orderItems = OrderItem::findRowsByOrderNo($orderNo);
                $orderItems = array_combine(array_column($orderItems, 'id'), $orderItems);
                $parentContain = [];
                foreach ($orderItems as $id => $value) {
                    if ($value['parent_id'] > 0) {
                        $pid = $value['parent_id'];
                        while ($orderItems[$pid]['parent_id'] > 0) {
                            $pid = $orderItems[$pid]['parent_id'];
                        }
                        $parentContain[$pid][$id] = $value['item_number'];
                    }
                }
                foreach ($parentContain as $pid => $idList) {
                    // 总乘数
                    $total = bcmul($orderItems[$pid]['item_pur_price'], $orderItems[$pid]['item_number'], 2);
                    // 计算权重计值基数
                    $baseAmount = $total;
                    if ($orderItems[$pid]['item_type'] & OrderItem::ITEM_TYPE_COMBINATION) {
                        // 带组合商品属性的商品事实上并不是单位商品，不计入权重计值基数
                        $baseAmount = 0;
                    }
                    foreach ($idList as $sid => $num) {
                        if ($orderItems[$sid]['item_type'] & OrderItem::ITEM_TYPE_COMBINATION) {
                            // 带组合商品属性的商品事实上并不是单位商品，不计入权重计值基数
                            continue;
                        }
                        $add = bcmul($orderItems[$sid]['item_pur_price'], $orderItems[$sid]['item_number'], 2);
                        $baseAmount = bcadd($baseAmount, $add, 2);
                    }
                    // 计算父项权重价格
                    if ($orderItems[$pid]['item_type'] & OrderItem::ITEM_TYPE_COMBINATION) {
                        // 带组合商品属性的商品事实上并不是单位商品，权重价格设为0
                        $price[$xferNo][$orderItems[$pid]['order_no']][$pid] = 0;
                    } else {
                        $price[$xferNo][$orderItems[$pid]['order_no']][$pid] = round(
                            $orderItems[$pid]['item_pur_price'] / $baseAmount * $total, 2
                        );
                    }
                    // 计算父项对应子项的权重价格
                    foreach ($idList as $sid => $num) {
                        if ($orderItems[$sid]['item_type'] & OrderItem::ITEM_TYPE_COMBINATION) {
                            // 带组合商品属性的商品事实上并不是单位商品，权重价格设为0
                            $price[$xferNo][$orderItems[$sid]['order_no']][$sid] = 0;
                        } else {
                            $price[$xferNo][$orderItems[$sid]['order_no']][$sid] = round(
                                $orderItems[$sid]['item_pur_price'] / $baseAmount * $total, 2
                            );
                        }
                    }
                }
            }

            if (!isset($price[$xferNo])) {
                $price[$xferNo] = [];
            }
            return $price[$xferNo];
        };

        // 推送
        $dataPush = function ($listChunk) use ($processor) {
            // 使用READ_COMMITTED级别事务，不能使用READ_UNCOMMITTED，避免其他任务脏读
            $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
            if ($task->getLevel() > 1) {
                $task->rollBack();
                throw new LogisException('禁止使用SAVEPOINT，请保证dispatch方法不在事务内回调');
            }
            try {
                // 请求参数
                $reqParams = [
                    'shop_no' => $processor->channelAlias,
                    'switch' => 1,
                ];

                // 更新数据
                $datetime = date('Y-m-d H:i:s');
                $data = [];
                foreach ($listChunk as $value) {
                    $data[] = [
                        'channel_id' => $processor->channelId,
                        'wdt_tid' => $value['tid'],
                        'wdt_push_data' => json_encode([
                            'shop_no' => $reqParams['shop_no'],
                            'switch' => $reqParams['switch'],
                            'trade_list' => [$value]
                        ], JSON_UNESCAPED_UNICODE),
                        'wdt_push_time' => $datetime,
                    ];
                }
                // 更新旺店通推送订单数据表
                WangdiantongPushedOrder::createRows($data);

                // 请求同步
                $reqParams['trade_list'] = json_encode($listChunk, JSON_UNESCAPED_UNICODE);
                $this->request($reqParams, $this->params['createApi']);

                $task->commit();
            } catch (\Exception $e) {
                $task->rollBack();
                Yii::info(
                    $e->getMessage() . '|' . json_encode($listChunk, JSON_UNESCAPED_UNICODE),
                    __METHOD__
                );
            } catch (\Throwable $e) {
                $task->rollBack();
                Yii::info(
                    $e->getMessage() . '|' . json_encode($listChunk, JSON_UNESCAPED_UNICODE),
                    __METHOD__
                );
            }
        };

        // 组合数据
        $listChunk = [];
        foreach ($orders as $order) {

            // 提取订单送货数据（重复会走缓存）
            $orderShipping = OrderShipping::findRowsByOrderNo($order['order_no']);
            $orderShipping = end($orderShipping);

            // 提取支付订单数据（重复会走缓存）
            $transfer = Transfer::findRowsByOrderNo($order['order_no']);
            $transfer = end($transfer);

            // 订单状态
            $trade_status = 10; // 未确认
            if ($order['order_stat'] == Order::TO_BE_PAID) {
                $trade_status = 10; // 待付款
            } elseif ($order['order_stat'] == Order::PAID
                && $orderShipping['ship_stat'] == OrderShipping::TO_BE_SHIPPED) {
                $trade_status = 30; // 已付款
            } elseif ($order['order_stat'] == Order::PAID && $orderShipping['ship_stat'] == OrderShipping::SHIPPED) {
                $trade_status = 50; // 已发货
            } elseif ($order['order_stat'] >= Order::COMPLETE) {
                $trade_status = 70; // 已完成
            } elseif ($order['order_stat'] == Order::REFUNDED) {
                $trade_status = 80; // 已退款
            } elseif ($order['order_stat'] == Order::CANCELED) {
                $trade_status = 90; // 订单已关闭（已取消）
            }

            // 地区
            District::$table = $orderShipping['dist_table'];
            $district = District::findDetailedDistrictById($orderShipping['dist_id']);

            $tradeList = [                                                                // 星号前缀为必填参数
                'tid' => $order['order_no'],                                              // *原始单号
                'trade_status' => $trade_status,                                          // *订单状态
                'pay_status' => $transfer['xfer_stat'] == Transfer::PAID ? 2 : 0,         // 支付状态（0未付，2已付）
                'delivery_term' => 1,                                                     // *发货条件（1为付款发货）
                'trade_time' => $order['create_time'],                                    // 下单时间
                'pay_time' => null,                                                       // 支付时间（取支付通知时间）
                'buyer_nick' => $orderShipping['consignee'],                              // *客户名
                'receiver_name' => $orderShipping['consignee'],                           // 收件人
                'receiver_province' => array_shift($district)['name'],            // *收货省份
                'receiver_city' => array_shift($district)['name'],                // *收货城市
                'receiver_district' => array_shift($district)['name'],            // *收货区县
                'receiver_address' => $orderShipping['addr_detail'],                      // *收货详细地址
                'receiver_mobile' => $orderShipping['phone_no'],                          // 手机号码
                'receiver_zip' => $orderShipping['zip_code'] ?: '',                       // 收件人邮编
                'logistics_type' => -1,                                                   // 物流方式（-1由ERP策略选择）
                'buyer_message' => $orderShipping['message'] ?: '',                       // 买家备注
                'post_amount' => 0,                                                       // *邮费（免费，包邮）
                'cod_amount' => 0,                                                        // *货到付款金额（不支持）
                'ext_cod_fee' => 0,                                                       // *货到付款买家费用（不支持）
                'other_amount' => 0,                                                      // *其它收费（暂无）
                'paid' => $order['order_stat'] >= Order::PAID ? $order['order_amt'] : 0,  // *客户已付金额
                'order_list' => null,
            ];
            if (!empty($transfer['xfer_ntime'])) {
                $tradeList['pay_time'] = $transfer['xfer_ntime'];
            } else {
                unset($tradeList['pay_time']);
            }

            // 提取订单商品数据（重复会走缓存）
            $orderItems = OrderItem::findRowsByOrderNo($order['order_no']);

            $orderList = [];
            foreach ($orderItems as $orderItem) {

                // 推送旺店通时，去除掉商品类型为组合商品的父商品，因为实际上并不是一个商品的最小单位
                if ($orderItem['item_type'] & OrderItem::ITEM_TYPE_COMBINATION) {
                    continue;
                }

                // 计算退款状态
                if (isset($relOrderItemIds[$orderItem['id']])) {
                    $refundStatus = 1;
                    foreach ($relOrderItemIds[$orderItem['id']] as $i) {
                        if ($refunds[$relations[$i]['refund_id']]['refund_stat'] == Refund::UNDER_REVIEW
                            && $refundStatus < 2) {
                            $refundStatus = 2;
                        } elseif ($refunds[$relations[$i]['refund_id']]['refund_stat'] == Refund::REFUNDED
                            && $refundStatus < 5) {
                            $refundStatus = 5;
                        }
                    }
                } else {
                    $refundStatus = 0;
                }

                $orderList[] = [
                    'oid' => $tradeList['tid'] . '_' . $orderItem['id'] . '_' . $orderItem['item_id'], // 子订单编号
                    'num' => $orderItem['item_number'],
                    'price' => $orderItemsWeightedPrice($transfer['xfer_no'])[$order['order_no']][$orderItem['id']],
                    'status' => $tradeList['trade_status'],
                    'refund_status' => $refundStatus,
                    'goods_id' => $orderItem['id'],
                    'goods_no' => $orderItem['item_sn'],
                    'goods_name' => $orderItem['item_name'],
                    'spec_name' => $orderItem['item_id'],
                ];
            }

            // 计算预计支付总额
            $sumBase = 0;
            foreach ($orderList as $value) {
                $add = bcmul($value['price'], $value['num'], 2);
                $sumBase = bcadd($sumBase, $add, 2);
            }

            // 计算实际支付查额
            $balance = bcsub($order['order_amt'], $sumBase, 2);

            if ($balance < 0) { // 分摊优惠（按权重）
                $remain = $discount = abs($balance);
                foreach ($orderList as &$value) {
                    $value['adjust_amount'] = 0;
                    $value['discount'] = 0;
                    $value['share_discount'] = round(
                        $value['price'] * $value['num'] / $sumBase * $discount, 2
                    );
                    if ($remain - $value['share_discount'] < 0) {
                        $value['share_discount'] = $remain;
                        $remain = 0;
                    } else {
                        $remain = bcsub($remain, $value['share_discount'], 2);
                    }
                }
            } elseif ($balance > 0) { // 调整（按权重）
                $remain = $balance;
                foreach ($orderList as &$value) {
                    $value['adjust_amount'] = round(
                        $value['price'] * $value['num'] / $sumBase * $balance, 2
                    );
                    if ($remain - $value['adjust_amount'] < 0) {
                        $value['adjust_amount'] = $remain;
                        $remain = 0;
                    } else {
                        $remain = bcsub($remain, $value['adjust_amount'], 2);
                    }
                    $value['discount'] = 0;
                    $value['share_discount'] = 0;
                }
            } else {
                $value['adjust_amount'] = 0;
                $value['discount'] = 0;
                $value['share_discount'] = 0;
            }

            $tradeList['order_list'] = $orderList;
            $listChunk[] = $tradeList;
            if (count($listChunk) >= 20) { // 一次不能超过50条，取20条同步一次，粒化处理
                $dataPush($listChunk);
                $listChunk = [];
            }
        }

        // 将剩余订单推送
        if (!empty($listChunk)) {
            $dataPush($listChunk);
        }
    }

    /**
     * 与ERP数据进行同步
     * 调用时注意区分渠道
     * @throws LogisException
     */
    public function synchronize()
    {
        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        $limit = 100; // 限制循环次数
        do {
            $limit--;

            // 查询物流同步
            $reqParams = [
                'limit' => 100,
                'shop_no' => $processor->channelAlias,
            ];
            $result = $this->request($reqParams, $this->params['queryApi']);
            if (empty($result['trades'])) {
                return;
            }

            // 每次循环释放各订单模型寄存器占用内存
            OrderShipping::clearRegister();
            WangdiantongPushedOrder::clearRegister();

            // 查询数据
            $tids = array_column($result['trades'], 'tid');
            OrderShipping::findRowsByOrderNo($tids); // wdt_tid与order_no相同
            $wdtOrders = WangdiantongPushedOrder::findRowsByChannelIdAndTid($processor->channelId, $tids);
            $tmp = [];
            foreach ($wdtOrders as $row) {
                $tmp[$row['wdt_tid']][] = $row;
            }
            $wdtOrders = $tmp;
            unset($tmp);

            // 同步数据
            $chunks = array_chunk($result['trades'], 20); // 分块事务，粒化处理，避免卡死一大片数据无法处理
            foreach ($chunks as $unit) {
                $errorMessage = '';
                // 使用READ_COMMITTED级别事务，不能使用READ_UNCOMMITTED，避免其他任务脏读
                $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
                if ($task->getLevel() > 1) {
                    $task->rollBack();
                    throw new LogisException('禁止使用SAVEPOINT，请保证synchronize方法不在事务内回调');
                }
                try {
                    // 获取执行时间
                    $datetime = date('Y-m-d H:i:s');

                    // 更新数据
                    foreach ($unit as $value) {
                        if (empty($wdtOrders[$value['tid']])) {
                            // 不存在的订单放入待插入数组
                            $insertLater[] = [
                                'channel_id' => $processor->channelId,
                                'wdt_tid' => $value['tid'],
                                'wdt_rec_id' => $value['rec_id'],
                                'wdt_shop_no' => $value['shop_no'],
                                'wdt_logistics_no' => $value['logistics_no'],
                                'wdt_logistics_type' => $value['logistics_type'],
                                'wdt_consign_time' => $value['consign_time'],
                                'wdt_platform_id' => $value['platform_id'],
                                'wdt_trade_id' => $value['trade_id'],
                                'wdt_logistics_code_erp' => $value['logistics_code_erp'],
                                'wdt_logistics_name_erp' => $value['logistics_name_erp'],
                                'wdt_logistics_name' => $value['logistics_name'],
                                'wdt_sync_data' => json_encode(
                                    [['datetime' => $datetime, 'trades' => $value]],  JSON_UNESCAPED_UNICODE
                                ),
                                'wdt_sync_time' => $datetime,
                            ];
                        } else {
                            foreach ($wdtOrders[$value['tid']] as $wdtorder) {
                                $newSyncData = json_encode(
                                    [['datetime' => $datetime, 'trades' => $value]],  JSON_UNESCAPED_UNICODE
                                );
                                if (!empty($wdtorder['wdt_sync_data'])) {
                                    $syncData = json_decode($wdtorder['wdt_sync_data'], true);
                                    if (is_array($syncData) && !empty($syncData)) {
                                        $lastSyncData = end($syncData);
                                        if ($lastSyncData['trades'] == $value) {
                                            continue;
                                        } else {
                                            $syncData[] = ['datetime' => $datetime, 'trades' => $value];
                                            $newSyncData = json_encode($syncData, JSON_UNESCAPED_UNICODE);
                                        }
                                    }
                                }

                                // 更新同步数据
                                $updateParams = [
                                    'wdt_rec_id' => $value['rec_id'],
                                    'wdt_shop_no' => $value['shop_no'],
                                    'wdt_logistics_no' => $value['logistics_no'],
                                    'wdt_logistics_type' => $value['logistics_type'],
                                    'wdt_consign_time' => $value['consign_time'],
                                    'wdt_platform_id' => $value['platform_id'],
                                    'wdt_trade_id' => $value['trade_id'],
                                    'wdt_logistics_code_erp' => $value['logistics_code_erp'],
                                    'wdt_logistics_name_erp' => $value['logistics_name_erp'],
                                    'wdt_logistics_name' => $value['logistics_name'],
                                    'wdt_sync_data' => $newSyncData,
                                    'wdt_sync_time' => $datetime,
                                ];
                                $num = WangdiantongPushedOrder::updateRowsById($updateParams, $wdtorder['id']);
                                if ($num != 1) {
                                    throw new LogisException('同步数据更新失败');
                                }
                            }
                        }

                        // 更新订单送货状态
                        if (!empty($value['logistics_no'])) {
                            $orderShipping = OrderShipping::findRowsByOrderNo($value['tid']);
                            if (!empty($orderShipping)) {
                                $orderShipping = end($orderShipping);
                                if (empty($orderShipping['ship_sn']) ||
                                    $orderShipping['ship_sn'] != $value['logistics_no']) {

                                    $num = 0;
                                    $loop = 3; // 尝试3轮
                                    do {
                                        $loop--;
                                        if ($loop < 0) break;
                                        if ($loop != 2) usleep(500000); // 一轮等待半秒
                                        $num = OrderShipping::updateStatByShipNo(
                                            $orderShipping['ship_no'],
                                            new Expression('(
                                                CASE WHEN 
                                                    `ship_stat` = ' . OrderShipping::TO_BE_SHIPPED . ' 
                                                THEN 
                                                    ' . OrderShipping::SHIPPED . ' 
                                                ELSE 
                                                    `ship_stat` 
                                                END
                                            )'),
                                            ['<', 'locked_time', $datetime],
                                            [
                                                'ship_sn'=> trim($value['logistics_no']),
                                                'ship_way' => trim(
                                                    $value['logistics_name'] ?: $value['logistics_name_erp']
                                                ),
                                                'ship_ntime' => trim($value['consign_time']),
                                            ]
                                        );
                                    } while ($num <= 0);

                                    if ($num <= 0) {
                                        throw new LogisException('更新订单送货状态失败');
                                    }
                                }
                            }
                        }
                    }

                    // 补写数据
                    if (!empty($insertLater)) {
                        WangdiantongPushedOrder::createRows($insertLater);
                    }

                    $task->commit();
                } catch (\Exception $e) {
                    $task->rollBack();
                    $errorMessage = $e->getMessage();
                    Yii::info(
                        $errorMessage . '|' . json_encode($unit, JSON_UNESCAPED_UNICODE),
                        __METHOD__
                    );
                } catch (\Throwable $e) {
                    $task->rollBack();
                    $errorMessage = $e->getMessage();
                    Yii::info(
                        $errorMessage . '|' . json_encode($unit, JSON_UNESCAPED_UNICODE),
                        __METHOD__
                    );
                }

                // 通知旺店通数据同步情况（通知放最后，因为就算通知失败，成功的可以重新再更新一次）
                $logisticsList = [];
                if (empty($errorMessage)) {
                    array_walk($unit, function ($v) use (&$logisticsList) {
                        $logisticsList[] = [
                            'rec_id' => $v['rec_id'],
                            'status' => 0,
                            'message' => '同步成功'
                        ];
                    });
                } else {
                    array_walk($unit, function ($v) use (&$logisticsList, $errorMessage) {
                        $logisticsList[] = [
                            'rec_id' => $v['rec_id'],
                            'status' => 1,
                            'message' => $errorMessage
                        ];
                    });
                }
                $reqParams = ['logistics_list' => json_encode($logisticsList, JSON_UNESCAPED_UNICODE)];
                $this->request($reqParams, $this->params['confirmApi']);
            }
        } while ($limit < 0);
    }
}