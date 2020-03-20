<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\FullReduce;
use app\modules\order\modules\v1\models\OrderAdjustment;

/**
 * 满减工具
 * Class PriceBreakTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class PriceBreakTool extends Behavior
{
    /**
     * 符合满减活动扣除订单总金额
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @return array
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        $index = 0;
        $method = __METHOD__;
        $func = function ($func) use (&$orders, &$orderItems, &$orderShipping, $method) {
            return function ($isExec = true) use ($func, &$orders, &$orderItems, &$orderShipping, $method) {
                if ($isExec) {
                    $datetime = date('Y-m-d H:i:s');
                    $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                    $priceBreakActivities = FullReduce::find()
                        ->where([
                            'AND',
                            ['=', 'record_status', 1],
                            ['=', 'wx_client_code', $channelAlias],
                            ['<=', 'begin_date', $datetime],
                            ['>=', 'end_date', $datetime]
                        ])
                        ->with('fullReduceDetail')
                        ->asArray()
                        ->all();

                    if (empty($priceBreakActivities)) {
                        $func(); return;
                    }

                    $cartItems = [];
                    foreach ($orderItems as $orderItem) {
                        if ($orderItem['parent_id'] == 0) {
                            $cartItems[$orderItem['item_id']] = [
                                'order_no' => $orderItem['order_no'],
                                'item_xfer_price' => $orderItem['item_xfer_price'],
                                'item_number' => $orderItem['item_number']
                            ];
                        }
                    }

                    $discount = []; // 订单扣除金额
                    foreach ($priceBreakActivities as $key => $activity) {
                        $priceBreakActivities[$key]['goods_id'] = explode(',', $activity['goods_id']);
                        if (!empty($activity['fullReduceDetail'])) {
                            $amount = []; // 活动对应商品总额和数量 - 按订单
                            foreach ($priceBreakActivities[$key]['goods_id'] as $itemId) {
                                if (isset($cartItems[$itemId])) {
                                    $itemCount = $cartItems[$itemId]['item_number'];
                                    $itemCost = bcmul(
                                        $cartItems[$itemId]['item_xfer_price'],
                                        $cartItems[$itemId]['item_number'],
                                        2
                                    );
                                    isset($amount[$cartItems[$itemId]['order_no']]['count']) ?
                                        $amount[$cartItems[$itemId]['order_no']]['count'] += $itemCount :
                                        $amount[$cartItems[$itemId]['order_no']]['count'] = $itemCount;
                                    isset($amount[$cartItems[$itemId]['order_no']]['cost']) ?
                                        $amount[$cartItems[$itemId]['order_no']]['cost'] = bcadd(
                                            $amount[$cartItems[$itemId]['order_no']]['cost'], $itemCost, 2
                                        ) :
                                        $amount[$cartItems[$itemId]['order_no']]['cost'] = $itemCost;
                                }
                            }
                            foreach ($amount as $orderNo => $value) {
                                $tmpDiscount = 0;
                                // 按最后一个符合要求的活动的配置进行扣除
                                $fullReduceDetail = end($activity['fullReduceDetail']);
                                if ($activity['reduce_type'] == 1) { // 满N元条件（满足条件只能减N元，不可打折）
                                    if ($value['cost'] >= $fullReduceDetail['min_amount']) {
                                        $tmpDiscount = bcadd(
                                            $tmpDiscount, $fullReduceDetail['reduce_amount'], 2
                                        );
                                    } else {
                                        while ($fullReduceDetail = prev($activity['fullReduceDetail'])) {
                                            if ($value['cost'] >= $fullReduceDetail['min_amount']) {
                                                $tmpDiscount = bcadd(
                                                    $tmpDiscount, $fullReduceDetail['reduce_amount'], 2
                                                );
                                                break;
                                            }
                                        }
                                    }
                                } elseif ($activity['reduce_type'] == 2) { // 满N件条件（可减N元或打N折）
                                    if ($value['count'] >= $fullReduceDetail['min_amount']) {
                                        if ($fullReduceDetail['type'] == 1) { // 减金额
                                            $tmpDiscount = bcadd(
                                                $tmpDiscount, $fullReduceDetail['reduce_amount'], 2
                                            );
                                        } elseif ($fullReduceDetail['type'] == 2) { // 打折
                                            $discountedCost = bcmul(
                                                $value['cost'], $fullReduceDetail['reduce_amount'], 2
                                            );
                                            $tmpDiscount = bcadd(
                                                $tmpDiscount, bcsub($value['cost'], $discountedCost, 2), 2
                                            );
                                        }
                                    } else {
                                        while ($fullReduceDetail = prev($activity['fullReduceDetail'])) {
                                            if ($value['count'] >= $fullReduceDetail['min_amount']) {
                                                if ($fullReduceDetail['type'] == 1) { // 减金额
                                                    $tmpDiscount = bcadd(
                                                        $tmpDiscount, $fullReduceDetail['reduce_amount'], 2
                                                    );
                                                } elseif ($fullReduceDetail['type'] == 2) { // 打折
                                                    $discountedCost = bcmul(
                                                        $value['cost'], $fullReduceDetail['reduce_amount'], 2
                                                    );
                                                    $tmpDiscount = bcadd(
                                                        $tmpDiscount,
                                                        bcsub($value['cost'], $discountedCost, 2),
                                                        2
                                                    );
                                                }
                                                break;
                                            }
                                        }
                                    }
                                }
                                if ($tmpDiscount > 0) {
                                    if (isset($discount[$orderNo])) {
                                        $discount[$orderNo] = bcadd($discount[$orderNo], $tmpDiscount, 2);
                                    } else {
                                        $discount[$orderNo] = $tmpDiscount;
                                    }
                                }
                            }
                        }
                    }

                    $discountRecord = []; // 调整记录

                    // 扣除订单金额
                    foreach ($orders as $i => $order) {
                        if (isset($discount[$order['order_no']])) {
                            $orders[$i]['order_amt'] = bcsub(
                                $orders[$i]['order_amt'], $discount[$order['order_no']], 2
                            );
                            $excess = 0;
                            if ($orders[$i]['order_amt'] < 0) {
                                $excess = $orders[$i]['order_amt'];
                                $orders[$i]['order_amt'] = 0;
                            }

                            $discountRecord[] = [
                                'order_no' => $order['order_no'],
                                'adjust_type' => OrderAdjustment::TYPE_DISCOUNT,
                                'adjust_name' => '满减活动优惠',
                                'adjust_detail' => '满减活动既得优惠总计',
                                'adjust_behavior' => $method,
                                'pre_adjust_amt' => bcmul($discount[$order['order_no']], -1, 2),
                                'act_adjust_amt' => bcmul(
                                    bcadd($discount[$order['order_no']], $excess, 2), -1, 2
                                ),
                                'create_time' => $datetime
                            ];
                        }
                    }

                    // 订单价格调整记录
                    if (!empty($discountRecord)) {
                        OrderAdjustment::createRows($discountRecord);
                    }
                }

                $func();
            };
        };

        return [$index => $func];
    }
}
