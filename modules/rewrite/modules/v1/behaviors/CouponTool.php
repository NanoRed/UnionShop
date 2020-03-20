<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\Good;
use app\modules\rewrite\modules\v1\models\CouponCode;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderAdjustment;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 现金券工具
 * Class CouponTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class CouponTool extends Behavior
{
    /**
     * 检验锁定现金券，扣除订单总金额
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @return array
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        $index = 2;
        $method = __METHOD__;
        $func = function ($func) use (&$orders, &$orderItems, &$orderShipping, $method) {
            return function ($isExec = true) use ($func, &$orders, &$orderItems, &$orderShipping, $method) {
                if ($isExec) {
                    $couponCodeId = Yii::$app->request->post('couponTicket');
                    if (!empty($couponCodeId)) {
                        if (!is_array($couponCodeId)) {
                            $couponCodeId = [$couponCodeId];
                        }

                        $couponCodeCount = count($couponCodeId);
                        if ($couponCodeCount > 1) { // 暂时只能使用一张现金券
                            throw new RewriteException('每次下单只能使用一张现金券');
                        }

                        if (array_sum(array_column($orders, 'order_amt')) <= 0) {
                            throw new RewriteException('当次购买需支付金额为0，为保障您的利益，请勿使用现金券');
                        }

                        $couponInfos = CouponCode::find()
                            ->with('couponEvent.coupon')
                            ->where(['IN', 'id', $couponCodeId])
                            ->asArray()
                            ->all();

                        if (count($couponInfos) != $couponCodeCount) {
                            throw new RewriteException('请勿使用无效现金券');
                        }

                        $timestamp = time();
                        $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);

                        $discount = []; // 现金券折扣
                        $isSplit = (bool)(count($orders) > 1); // 是否拆单，即当前购买是否生成多个订单
                        foreach ($couponInfos as $couponInfo) {
                            if (empty($couponInfo['couponEvent']) || empty($couponInfo['couponEvent']['coupon'])) {
                                throw new RewriteException('请勿使用无效现金券');
                            }
                            if ($couponInfo['is_use'] != 1) {
                                throw new RewriteException('请勿使用已被使用的现金券');
                            }
                            if ($couponInfo['user_id'] != $rewriteUserId) {
                                throw new RewriteException('请勿使用已被别人领取的现金券');
                            }
                            if (strtotime($couponInfo['couponEvent']['coupon']['start_date']) > $timestamp) {
                                throw new RewriteException('请勿使用尚未在有效期内的现金券');
                            }
                            if (strtotime($couponInfo['couponEvent']['coupon']['end_date']) + 86400 <= $timestamp) {
                                throw new RewriteException('请勿使用已过期的现金券');
                            }

                            $valid = false; // 是否通过验证
                            $errorMsg = '未知的现金券错误，请联系客服'; // 没有通过验证的错误信息
                            $couponType2ItemCheckMark = false; // 类型2（指定商品）现金券订单商品验证标记
                            $couponType3ItemCheckMark = false; // 类型3（指定经营模式）现金券订单商品经营模式验证标记
                            $validOrderNo = ''; // 消耗当前现金券对应的订单号
                            foreach ($orders as $i => $order) {
                                switch ($couponInfo['couponEvent']['coupon']['type']) {
                                    case 1: // 全场通用
                                        if ($isSplit) {
                                            if ($order['order_amt'] > 0
                                                && $order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                if (!$valid) {
                                                    $valid = true;
                                                    $validOrderNo = $order['order_no'];
                                                    if (isset($discount[$i])) {
                                                        if ($order['order_amt'] - $discount[$i] <= 0) {
                                                            throw new RewriteException(
                                                                '当前所选购的商品将自动拆分订单，'
                                                                . '而其中存在订单使现金券折扣金额超出了其须支付金额，'
                                                                . '为保障您的利益，请将商品分开购买或重新选择现金券'
                                                            );
                                                        } else {
                                                            $discount[$i] = bcadd(
                                                                $discount[$i],
                                                                $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                                2
                                                            );
                                                        }
                                                    } else {
                                                        $discount[$i]
                                                            = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                    }
                                                } else {
                                                    throw new RewriteException(
                                                        '当前所选购的商品将自动拆分订单，'
                                                        . '而其中存在多个订单符合同一张现金券的使用，'
                                                        . '为保障您的利益，请将商品分开购买再使用现金券'
                                                    );
                                                }
                                            } else { // 此处逻辑假设$valid最终为false
                                                $errorMsg = '当前所选购的商品将自动拆分订单，'
                                                    . '而其中所有非0元订单都未达到同一张现金券的使用要求，'
                                                    . '请将商品分开购买或重新选择现金券';
                                            }
                                        } else {
                                            if ($order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                $valid = true;
                                                $validOrderNo = $order['order_no'];
                                                if (isset($discount[$i])) {
                                                    if ($order['order_amt'] - $discount[$i] <= 0) {
                                                        throw new RewriteException(
                                                            '当前现金券折扣金额超出了订单须支付金额，'
                                                            . '为保障您的利益，请重新选择现金券'
                                                        );
                                                    } else {
                                                        $discount[$i] = bcadd(
                                                            $discount[$i],
                                                            $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                            2
                                                        );
                                                    }
                                                } else {
                                                    $discount[$i]
                                                        = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                }
                                            } else {
                                                $errorMsg = '当前订单未达到所选现金券的使用要求';
                                            }
                                        }
                                        break;
                                    case 2: // 指定商品
                                        if (!$couponType2ItemCheckMark) { // 一张现金券验证一次
                                            if (!isset($settleItemIds)) {
                                                $settleItemIds = [];
                                                foreach ($orderItems as $orderItem) {
                                                    if ($orderItem['parent_id'] == 0) {
                                                        $settleItemIds[] = $orderItem['item_id'];
                                                    }
                                                }
                                            }
                                            $couponItemValid = explode(
                                                ',',
                                                $couponInfo['couponEvent']['coupon']['goods_group']
                                            );
                                            $diff = array_diff($settleItemIds, $couponItemValid);
                                            if (!empty($diff)) {
                                                throw new RewriteException(
                                                    '订单内含所选指定商品现金券非指定的商品'
                                                );
                                            }
                                            $couponType2ItemCheckMark = true;
                                        }
                                        if ($isSplit) {
                                            if ($order['order_amt'] > 0
                                                && $order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                if (!$valid) {
                                                    $valid = true;
                                                    $validOrderNo = $order['order_no'];
                                                    if (isset($discount[$i])) {
                                                        if ($order['order_amt'] - $discount[$i] <= 0) {
                                                            throw new RewriteException(
                                                                '当前所选购的商品将自动拆分订单，'
                                                                . '而其中存在订单使现金券折扣金额超出了其须支付金额，'
                                                                . '为保障您的利益，请将商品分开购买或重新选择现金券'
                                                            );
                                                        } else {
                                                            $discount[$i] = bcadd(
                                                                $discount[$i],
                                                                $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                                2
                                                            );
                                                        }
                                                    } else {
                                                        $discount[$i]
                                                            = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                    }
                                                } else {
                                                    throw new RewriteException(
                                                        '当前所选购的商品将自动拆分订单，'
                                                        . '而其中存在多个订单符合同一张现金券的使用，'
                                                        . '为保障您的利益，请将商品分开购买再使用现金券'
                                                    );
                                                }
                                            } else { // 此处逻辑假设$valid最终为false
                                                $errorMsg = '当前所选购的商品将自动拆分订单，'
                                                    . '而其中所有非0元订单都未达到同一张现金券的使用要求，'
                                                    . '请将商品分开购买或重新选择现金券';
                                            }
                                        } else {
                                            if ($order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                $valid = true;
                                                $validOrderNo = $order['order_no'];
                                                if (isset($discount[$i])) {
                                                    if ($order['order_amt'] - $discount[$i] <= 0) {
                                                        throw new RewriteException(
                                                            '当前现金券折扣金额超出了订单须支付金额，'
                                                            . '为保障您的利益，请重新选择现金券'
                                                        );
                                                    } else {
                                                        $discount[$i] = bcadd(
                                                            $discount[$i],
                                                            $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                            2
                                                        );
                                                    }
                                                } else {
                                                    $discount[$i]
                                                        = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                }
                                            } else {
                                                $errorMsg = '当前订单未达到所选现金券的使用要求';
                                            }
                                        }
                                        break;
                                    case 3: // 指定经营模式
                                        if (!$couponType3ItemCheckMark) { // 一张现金券验证一次
                                            if (!isset($settleItemIds)) {
                                                $settleItemIds = [];
                                                foreach ($orderItems as $orderItem) {
                                                    if ($orderItem['parent_id'] == 0) {
                                                        $settleItemIds[] = $orderItem['item_id'];
                                                    }
                                                }
                                            }
                                            if (!isset($itemsBusinessMode)) {
                                                $itemsBusinessMode = Good::find()
                                                    ->select(['business_model'])
                                                    ->where(['IN', 'goods_id', $settleItemIds])
                                                    ->asArray()
                                                    ->column();
                                            }
                                            foreach ($itemsBusinessMode as $businessMode) {
                                                if ($businessMode
                                                    != $couponInfo['couponEvent']['coupon']['goods_group']) {
                                                    throw new RewriteException(
                                                        '订单内含所选指定经营模式现金券非指定经营模式的商品'
                                                    );
                                                }
                                            }
                                            $couponType3ItemCheckMark = true;
                                        }
                                        if ($isSplit) {
                                            if ($order['order_amt'] > 0
                                                && $order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                if (!$valid) {
                                                    $valid = true;
                                                    $validOrderNo = $order['order_no'];
                                                    if (isset($discount[$i])) {
                                                        if ($order['order_amt'] - $discount[$i] <= 0) {
                                                            throw new RewriteException(
                                                                '当前所选购的商品将自动拆分订单，'
                                                                . '而其中存在订单使现金券折扣金额超出了其须支付金额，'
                                                                . '为保障您的利益，请将商品分开购买或重新选择现金券'
                                                            );
                                                        } else {
                                                            $discount[$i] = bcadd(
                                                                $discount[$i],
                                                                $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                                2
                                                            );
                                                        }
                                                    } else {
                                                        $discount[$i]
                                                            = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                    }
                                                } else {
                                                    throw new RewriteException(
                                                        '当前所选购的商品将自动拆分订单，'
                                                        . '而其中存在多个订单符合同一张现金券的使用，'
                                                        . '为保障您的利益，请将商品分开购买再使用现金券'
                                                    );
                                                }
                                            } else { // 此处逻辑假设$valid最终为false
                                                $errorMsg = '当前所选购的商品将自动拆分订单，'
                                                    . '而其中所有非0元订单都未达到同一张现金券的使用要求，'
                                                    . '请将商品分开购买或重新选择现金券';
                                            }
                                        } else {
                                            if ($order['order_amt']
                                                >= $couponInfo['couponEvent']['coupon']['limit_price']) {
                                                $valid = true;
                                                $validOrderNo = $order['order_no'];
                                                if (isset($discount[$i])) {
                                                    if ($order['order_amt'] - $discount[$i] <= 0) {
                                                        throw new RewriteException(
                                                            '当前现金券折扣金额超出了订单须支付金额，'
                                                            . '为保障您的利益，请重新选择现金券'
                                                        );
                                                    } else {
                                                        $discount[$i] = bcadd(
                                                            $discount[$i],
                                                            $couponInfo['couponEvent']['coupon']['reduce_price'],
                                                            2
                                                        );
                                                    }
                                                } else {
                                                    $discount[$i]
                                                        = $couponInfo['couponEvent']['coupon']['reduce_price'];
                                                }
                                            } else {
                                                $errorMsg = '当前订单未达到所选现金券的使用要求';
                                            }
                                        }
                                        break;
                                }
                            }

                            if ($valid) {
                                // 冻结现金券
                                $num = CouponCode::updateAll(
                                    ['order_sn' => $validOrderNo, 'is_use' => CouponCode::FROZEN],
                                    ['id' => $couponInfo['id'], 'is_use' => CouponCode::UNTAPPED]
                                );
                                if ($num != 1) {
                                    throw new RewriteException('现金券使用失败，请重试再试');
                                }
                            } else {
                                throw new RewriteException($errorMsg);
                            }
                        }

                        if (!empty($discount)) {
                            foreach ($discount as $i => $deduct) {
                                // 扣除订单金额
                                $orders[$i]['order_amt'] = bcsub($orders[$i]['order_amt'], $deduct, 2);
                                $excess = 0;
                                if ($orders[$i]['order_amt'] < 0) {
                                    $excess = $orders[$i]['order_amt'];
                                    $orders[$i]['order_amt'] = 0;
                                }

                                // 订单价格调整记录
                                $discount[$i] = [
                                    'order_no' => $orders[$i]['order_no'],
                                    'adjust_type' => OrderAdjustment::TYPE_DISCOUNT,
                                    'adjust_name' => '现金券抵额',
                                    'adjust_detail' => '现金券抵额优惠总计',
                                    'adjust_behavior' => $method,
                                    'pre_adjust_amt' => bcmul($deduct, -1, 2),
                                    'act_adjust_amt' => bcmul(
                                        bcadd($deduct, $excess, 2), -1, 2
                                    ),
                                    'create_time' => date('Y-m-d H:i:s', $timestamp)
                                ];
                            }
                            OrderAdjustment::createRows($discount);
                        }
                    }
                }

                $func();
            };
        };

        return [$index => $func];
    }

    /**
     * 接收支付通知时更新现金券为已使用状态
     * @param $orderNo
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function marketGetPaymentNotification($orderNo)
    {
        $orders = Order::findRowsByOrderNo($orderNo);
        $rewriteUserId = User::findRewriteUserIdByUnionUserId(reset($orders)['user_id']);
        CouponCode::updateStatByOrderSn(
            $orderNo, CouponCode::USED, ['AND', 'user_id' => $rewriteUserId, 'is_use' => CouponCode::FROZEN]
        );
    }

    /**
     * 取消订单时恢复现金券为未使用状态
     * @param $orderNo
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function marketOrderCancel($orderNo)
    {
        $orders = Order::findRowsByOrderNo($orderNo);
        $rewriteUserId = User::findRewriteUserIdByUnionUserId(reset($orders)['user_id']);
        CouponCode::updateStatByOrderSn(
            $orderNo, CouponCode::UNTAPPED, ['AND', 'user_id' => $rewriteUserId, 'is_use' => CouponCode::FROZEN]
        );
    }
}
