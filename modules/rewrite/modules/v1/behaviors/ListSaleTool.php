<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\ChooseNumber;
use app\modules\order\modules\v1\models\OrderAdjustment;

/**
 * N元N件工具
 * Class ListSaleTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class ListSaleTool extends Behavior
{
    /**
     * 符合N元N件活动扣除订单总金额
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @return array
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        $index = 1;
        $method = __METHOD__;
        $func = function ($func) use (&$orders, &$orderItems, &$orderShipping, $method) {
            return function ($isExec = true) use ($func, &$orders, &$orderItems, &$orderShipping, $method) {
                if ($isExec) {
                    $datetime = date('Y-m-d H:i:s');
                    $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                    $pickListSaleActivities = ChooseNumber::find()
                        ->where([
                            'AND',
                            ['=', 'record_status', 1],
                            ['=', 'channel_code', $channelAlias],
                            ['<=', 'start_time', $datetime],
                            ['>=', 'end_time', $datetime]
                        ])
                        ->asArray()
                        ->all();

                    if (empty($pickListSaleActivities)) {
                        $func(); return;
                    }

                    $cartOrderItems = [];
                    foreach ($orderItems as $orderItem) {
                        if ($orderItem['parent_id'] == 0) {
                            $cartOrderItems[$orderItem['order_no']][] = [
                                'item_id' => $orderItem['item_id'],
                                'item_xfer_price' => $orderItem['item_xfer_price'],
                                'item_number' => $orderItem['item_number']
                            ];
                        }
                    }

                    $discount = [];
                    foreach ($pickListSaleActivities as $key => $activity) {
                        $activity[$key]['goods_id'] = explode(',', $activity['goods_id']);
                        foreach ($cartOrderItems as $orderNo => $items) {
                            // 排序，按最大折扣算
                            array_multisort(
                                $items ,SORT_DESC, array_column($items, 'item_xfer_price')
                            );
                            $number = $amount = 0;
                            foreach ($items as $item) {
                                if (in_array($item['item_id'], $activity[$key]['goods_id'])) {
                                    for ($i = 1; $i <= $item['item_number']; $i++) {
                                        $number++;
                                        $amount = bcadd($amount, $item['item_xfer_price'], 2);
                                        if ($number >= $activity['number']) {
                                            $balance = bcsub($amount, $activity['total_amount'], 2);
                                            if (isset($discount[$orderNo])) {
                                                $discount[$orderNo] = bcadd($discount[$orderNo], $balance, 2);
                                            } else {
                                                $discount[$orderNo] = $balance;
                                            }
                                            $number = $amount = 0;
                                        }
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
                                'adjust_name' => 'N元N件活动优惠',
                                'adjust_detail' => 'N元N件活动既得优惠总计',
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
