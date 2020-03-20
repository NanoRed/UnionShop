<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\Activity;
use app\modules\rewrite\modules\v1\models\ActivityMember;
use app\modules\rewrite\modules\v1\models\GoldActivityMember;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 活动限购
 * Class ActivityLimitTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class ActivityLimitTool extends Behavior
{
    public $validActivities = null;
    public $memberPermitList = null;
    public $goldMemberPermitList = null;
    public $isNewUser = null;

    /**
     * 活动限购验证
     * @param $good
     * @param $cartItemsProperty
     * @return array
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        $obj = $this;
        $index = 1; // 执行顺序，值越小越先执行，注意值不要重复
        $func = function ($func) use ($good, $cartItemsProperty, $obj) {
            return function ($isExec = true) use ($func, $good, $cartItemsProperty, $obj) {
                if ($isExec) {
                    $timestamp = time();
                    $datetime = date('Y-m-d H:i:s', $timestamp);
                    if ($obj->validActivities === null) { // 减少IO
                        $obj->validActivities = Activity::find()
                            ->where([
                                'AND',
                                ['<=', 'begin_time', $datetime],
                                ['>=', 'end_time', $datetime],
                                ['=', 'record_status', 1]
                            ])
                            ->indexBy('id')
                            ->asArray()
                            ->all();
                        foreach ($obj->validActivities as $key => $value) {
                            $obj->validActivities[$key]['goods_id'] = explode(',', $value['goods_id']);
                        }
                    }

                    foreach ($obj->validActivities as $activityId => $activityInfo) {
                        if (in_array($good['goods_id'], $activityInfo['goods_id'])) {
                            switch ($activityInfo['activity_type']) {
                                case '1': // 普通活动
                                    if ($obj->memberPermitList === null) { // 减少IO
                                        $obj->memberPermitList = ActivityMember::find()
                                            ->where([
                                                'AND',
                                                ['=', 'user_alias_new', Yii::$app->user->identity->cuid],
                                                ['IN', 'activity_id', array_keys($obj->validActivities)],
                                                ['IN', 'goods_id', array_keys($cartItemsProperty)],
                                                ['=', 'record_status', 1],
                                            ])
                                            ->asArray()
                                            ->all();
                                        $temp = [];
                                        foreach ($obj->memberPermitList as $key => $value) {
                                            $temp[$value['activity_id']][$value['goods_id']] = true;
                                        }
                                        $obj->memberPermitList = $temp; unset($temp);
                                    }

                                    if (isset($obj->memberPermitList[$activityId][$good['goods_id']])) {
                                        $periodBegin = $activityInfo['begin_time'];
                                        $periodEnd = $activityInfo['end_time'];
                                        if (!empty($activityInfo['limit_period'])) { // 限购判断时间段
                                            switch ($activityInfo['limit_period']) {
                                                case 'day':
                                                    $bTimestamp = $timestamp;
                                                    $periodBegin = date('Y-m-d 00:00:00', $bTimestamp);
                                                    $eTimestamp = strtotime($periodBegin . ' +1 days');
                                                    $periodEnd = date('Y-m-d 00:00:00', $eTimestamp);
                                                    break;
                                                case 'week':
                                                    $bTimestamp = strtotime(
                                                        date('Y-m-d', $timestamp) .
                                                        ' -' . (date('N', $timestamp) - 1) . ' days'
                                                    );
                                                    $periodBegin = date('Y-m-d 00:00:00', $bTimestamp);
                                                    $eTimestamp = strtotime($periodBegin . ' +1 weeks');
                                                    $periodEnd = date('Y-m-d 00:00:00', $eTimestamp);
                                                    break;
                                                case 'month':
                                                    $bTimestamp = strtotime(
                                                        date('Y-m-d', $timestamp) .
                                                        ' -' . (date('j', $timestamp) - 1) . ' days'
                                                    );
                                                    $periodBegin = date('Y-m-d 00:00:00', $bTimestamp);
                                                    $eTimestamp = strtotime($periodBegin . ' +1 months');
                                                    $periodEnd = date('Y-m-d 00:00:00', $eTimestamp);
                                                    break;
                                                case 'year':
                                                    $bTimestamp = strtotime(
                                                        date('Y-m-d', $timestamp) .
                                                        ' -' . date('z', $timestamp) . ' days'
                                                    );
                                                    $periodBegin = date('Y-m-d 00:00:00', $bTimestamp);
                                                    $eTimestamp = strtotime($periodBegin . ' +1 years');
                                                    $periodEnd = date('Y-m-d 00:00:00', $eTimestamp);
                                                    break;
                                                default:
                                                    $bTimestamp = strtotime(
                                                        date('Y-m-d', $timestamp) .
                                                        ' -' . (date('j', $timestamp) - 1) . ' days'
                                                    );
                                                    $periodBegin = date('Y-m-d 00:00:00', $bTimestamp);
                                                    $eTimestamp = strtotime($periodBegin . ' +1 months');
                                                    $periodEnd = date('Y-m-d 00:00:00', $eTimestamp);
                                                    break;
                                            }
                                        }

                                        if ($activityInfo['count_limit'] > 0) { // 限购次数
                                            $purchasedTimes = (int)(OrderItem::find()
                                                ->alias('orderItem')
                                                ->joinWith('order order')
                                                ->where([
                                                    'AND',
                                                    ['=', 'orderItem.user_id', Yii::$app->user->identity->id],
                                                    ['>=', 'orderItem.create_time', $periodBegin],
                                                    ['<', 'orderItem.create_time', $periodEnd],
                                                    ['IN', 'orderItem.item_id', $activityInfo['goods_id']],
                                                    ['=', 'orderItem.parent_id', 0],
                                                    ['>=', 'order.order_stat', 0]
                                                ])
                                                ->count('distinct orderItem.order_no'));
                                            if ($purchasedTimes >= $activityInfo['count_limit']) {
                                                $errorMessage = $activityInfo['limit_tip'];
                                                if (empty($errorMessage)) {
                                                    $errorMessage = '抱歉，此次购买的活动商品已超出了活动限购次数'
                                                        . '（限购次数：少于' . $activityInfo['count_limit'] . '次）';
                                                }
                                                throw new RewriteException($errorMessage);
                                            }
                                        }

                                        if ($activityInfo['activity_limit'] > 0) { // 限购数量
                                            $purchasedQty = 0;
                                            foreach ($activityInfo['goods_id'] as $activityGoodId) {
                                                if (isset($cartItemsProperty[$activityGoodId])) {
                                                    $purchasedQty += $cartItemsProperty[$activityGoodId][2];
                                                }
                                            }
                                            $purchasedQty += (int)(OrderItem::find()
                                                ->alias('orderItem')
                                                ->joinWith('order order')
                                                ->where([
                                                    'AND',
                                                    ['=', 'orderItem.user_id', Yii::$app->user->identity->id],
                                                    ['>=', 'orderItem.create_time', $periodBegin],
                                                    ['<', 'orderItem.create_time', $periodEnd],
                                                    ['IN', 'orderItem.item_id', $activityInfo['goods_id']],
                                                    ['=', 'orderItem.parent_id', 0],
                                                    ['>=', 'order.order_stat', 0]
                                                ])
                                                ->sum('orderItem.item_number'));
                                            if ($purchasedQty > $activityInfo['activity_limit']) {
                                                $errorMessage = $activityInfo['limit_tip'];
                                                if (empty($errorMessage)) {
                                                    $errorMessage = '抱歉，此次购买的活动商品已超出了活动限购数量'
                                                        . '（限购数量：' . $activityInfo['activity_limit'] . '件）';
                                                }
                                                throw new RewriteException($errorMessage);
                                            }
                                        }
                                    } else {
                                        $errorMessage = $activityInfo['tip'];
                                        if (empty($errorMessage)) {
                                            $errorMessage = '抱歉，您未有此次活动商品的购买资格';
                                        }
                                        throw new RewriteException($errorMessage);
                                    }
                                    break;
                                case '2': // 积分金币活动
                                    if ($obj->goldMemberPermitList === null) { // 减少IO
                                        $obj->goldMemberPermitList = GoldActivityMember::find()
                                            ->where([
                                                'AND',
                                                ['=', 'user_alias_new', Yii::$app->user->identity->cuid],
                                                ['IN', 'activity_id', array_keys($obj->validActivities)],
                                            ])
                                            ->asArray()
                                            ->all();
                                        $temp = [];
                                        foreach ($obj->goldMemberPermitList as $key => $value) {
                                            $temp[$value['activity_id']][] = [
                                                'gold' => $value['gold'],
                                                'times' => $value['times'],
                                                'status' => $value['status'],
                                                'create_time' => $value['create_time'],
                                            ];
                                        }
                                        $obj->goldMemberPermitList = $temp; unset($temp);
                                    }

                                    if (!isset($obj->goldMemberPermitList[$activityId])) {
                                        $errorMessage = $activityInfo['tip'];
                                        if (empty($errorMessage)) {
                                            $errorMessage = '抱歉，您未有此次活动商品的购买资格';
                                        }
                                        throw new RewriteException($errorMessage);
                                    }
                                    break;
                                case '3': // 新用户活动
                                    if ($obj->isNewUser === null) { // 减少IO
                                        $purchasedOrders = (int)(Order::find()
                                            ->where([
                                                'AND',
                                                ['=', 'user_id', Yii::$app->user->identity->id],
                                                ['>', 'order_stat', 0]
                                            ])
                                            ->count());
                                        if ($purchasedOrders > 0) {
                                            $obj->isNewUser = false;
                                        } else {
                                            $obj->isNewUser = true;
                                        }
                                    }

                                    if (!$obj->isNewUser) {
                                        $errorMessage = $activityInfo['tip'];
                                        if (empty($errorMessage)) {
                                            $errorMessage = '抱歉，您未有此次活动商品的购买资格';
                                        }
                                        throw new RewriteException($errorMessage);
                                    }
                                    break;
                                case '4': // 老用户活动
                                    if ($obj->isNewUser === null) { // 减少IO
                                        $purchasedOrders = (int)(Order::find()
                                            ->where([
                                                'AND',
                                                ['=', 'user_id', Yii::$app->user->identity->id],
                                                ['>', 'order_stat', 0]
                                            ])
                                            ->count());
                                        if ($purchasedOrders > 0) {
                                            $obj->isNewUser = false;
                                        } else {
                                            $obj->isNewUser = true;
                                        }
                                    }

                                    if ($obj->isNewUser) {
                                        $errorMessage = $activityInfo['tip'];
                                        if (empty($errorMessage)) {
                                            $errorMessage = '抱歉，您未有此次活动商品的购买资格';
                                        }
                                        throw new RewriteException($errorMessage);
                                    }
                                    break;
                            }
                        }
                    }
                }

                $func();
            };
        };

        return [$index => $func];
    }
}
