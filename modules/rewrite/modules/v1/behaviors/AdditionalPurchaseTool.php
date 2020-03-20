<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\Expression;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\rewrite\modules\v1\models\Good;
use app\modules\rewrite\modules\v1\models\GoodLink;
use app\modules\rewrite\modules\v1\models\Code;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\UserCart;
use app\modules\rewrite\modules\v1\models\ExtraCost;
use app\modules\rewrite\modules\v1\models\SaleChannel;
use app\modules\user\modules\v1\models\UserAddress;
use app\modules\logis\modules\v1\models\OrderShipping;
use app\modules\order\modules\v1\models\OrderAdjustment;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 加价购工具
 * Class AdditionalPurchaseTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class AdditionalPurchaseTool extends Behavior
{
    /**
     * 添加加价购商品
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @throws RewriteException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     * @throws \yii\db\Exception
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);
        $cartInfo = UserCart::findCartItems($rewriteUserId);

        if (!empty($cartInfo['extra_cost_cookie'])) {
            // 提取购物车
            $additionalItemsInfo = [];
            $additionalItemCodes = array_filter(explode(',', $cartInfo['extra_cost_cookie']));
            foreach ($additionalItemCodes as $additionalItemCode) {
                // 1529（所从属的商品ID）-1526（此商品ID）-1（数量）
                $itemProperty = explode('-', $additionalItemCode);
                $additionalItemsInfo[] = [
                    'subordinate_to' => (int)$itemProperty[0],
                    'item_id' => (int)$itemProperty[1],
                    'item_number' => (int)$itemProperty[2],
                ];
            }
            if (empty($additionalItemsInfo)) {
                UserCart::updateAll(['extra_cost_cookie' => ''], ['user_id' => $rewriteUserId]);
                return;
            }

            // 获取执行时刻
            $timestamp = time();
            $datetime = date('Y-m-d H:i:s', $timestamp);

            // 查询加价购活动
            $rewriteChannel = SaleChannel::findRewriteChannel(
                Yii::$app->getModule('channel/v1')->processor->channelAlias
            );
            $additionalPurchaseActivities = ExtraCost::find()
                ->where([
                    'AND',
                    ['=', 'status', 1],
                    ['=', 'channel_id', $rewriteChannel['id']],
                    ['<=', 'start_date', $datetime],
                    ['>=', 'end_date', $datetime]
                ])
                ->with('extraCostReward')
                ->asArray()
                ->all();

            // 加价购活动验证
            $purchaseCount = []; // 加价购活动对应的加价购商品购买数量统计
            foreach ($additionalItemsInfo as $i => $additionalItem) {
                $matchedActivity = null;
                foreach ($additionalPurchaseActivities as $k => $additionalPurchaseActivity) {
                    if (!is_array($additionalPurchaseActivities[$k]['goods_group'])) {
                        $additionalPurchaseActivities[$k]['goods_group']
                            = explode(',', $additionalPurchaseActivity['goods_group']);
                    }
                    if (in_array($additionalItem['subordinate_to'], $additionalPurchaseActivities[$k]['goods_group'])) {
                        if (!empty($additionalPurchaseActivity['extraCostReward'])) {
                            foreach ($additionalPurchaseActivity['extraCostReward'] as $extraCostReward) {
                                if ($extraCostReward['goods_id'] == $additionalItem['item_id']) {
                                    $additionalItemsInfo[$i]['item_xfer_price'] = $extraCostReward['price'];
                                    $matchedActivity = $k;
                                }
                            }
                        }
                    }
                }
                if (isset($matchedActivity)) {
                    isset($purchaseCount[$matchedActivity][$additionalItem['subordinate_to']]) ?
                        $purchaseCount[$matchedActivity][$additionalItem['subordinate_to']]
                            += $additionalItem['item_number'] :
                        $purchaseCount[$matchedActivity][$additionalItem['subordinate_to']]
                            = $additionalItem['item_number'];
                } else {
                    $goodInfo = Good::find()
                        ->select(['goods_id', 'goods_name'])
                        ->where(['goods_id' => $additionalItem['item_id']])
                        ->with([
                            'goodExtra' => function ($query) {
                                $query->select(['goods_name']);
                            }
                        ])
                        ->asArray()
                        ->one();
                    $itemName = isset($goodInfo['goodExtra']['goods_name']) ?
                        $goodInfo['goodExtra']['goods_name'] :
                        $goodInfo['goods_name'];
                    throw new RewriteException('商品【' . $itemName . '】非加价购商品，请联系客服');
                }
            }
            $subordinateRecord = []; // 记录加价购父商品的偏移索引
            foreach ($purchaseCount as $i => $itemCount) {
                $num = 0;
                foreach ($orderItems as $k => $orderItem) {
                    if ($orderItem['parent_id'] == 0) {
                        if (isset($itemCount[$orderItem['item_id']])) {

                            // 为订单商品叠加加价购的商品类型
                            $orderItems[$k]['item_type'] |= OrderItem::ITEM_TYPE_ADDITIONAL_PURCHASE;

                            $subordinateRecord[$orderItem['item_id']] = $k;
                            if ($orderItem['item_number'] > 0) {
                                unset($purchaseCount[$i][$orderItem['item_id']]);
                                if (empty($purchaseCount[$i])) {
                                    unset($purchaseCount[$i]);
                                }
                                $num += $orderItem['item_number'];
                            }
                        }
                    }
                }
                if (!empty($purchaseCount[$i])) {
                    throw new RewriteException('未购买对应的活动商品，无法购买其加价购商品');
                }
                if ($additionalPurchaseActivities[$i]['limit_num'] > 0
                    && array_sum($itemCount) > $additionalPurchaseActivities[$i]['limit_num'] * $num) {
                    $errorMessage = $additionalPurchaseActivities[$i]['limit_num_tips'];
                    if (empty($errorMessage)) {
                        $errorMessage = '抱歉，对应加价购活动中每一件活动商品仅限加价购';
                        $errorMessage .= $additionalPurchaseActivities[$i]['limit_num'] . '件';
                    }
                    throw new RewriteException($errorMessage);
                }
            }

            // 获取商品信息，这是个递归闭包函数，原因是用于处理类组合商品
            $recurFunc = function (
                $itemsInfo, $pid = 0, $pType = OrderItem::ITEM_TYPE_ADDITIONAL_PURCHASE, $params = []
            ) use (&$recurFunc, $timestamp) {
                $goodsInfo = Good::find()
                    ->select([
                        'goods_id',        'goods_sn',     'goods_name',
                        'warehouse',       'supplier',     'shop_price',
                        'promote_price',   'is_virtual',   'goods_model',
                        'ref_goods_id',
                    ])
                    ->where(['IN', 'goods_id', array_column($itemsInfo, 'item_id')])
                    ->indexBy('goods_id')
                    ->with([
                        'goodExtra' => function ($query) {
                            $query->select([
                                'goods_id',     'goods_name',   'channel_price',
                                'start_date',   'end_date',     'on_sale'
                            ]);
                        }
                    ])
                    ->asArray()
                    ->all();
                for ($i = 0; $i < count($itemsInfo); $i++) {
                    // 商品基础检查
                    if ($itemsInfo[$i]['item_number'] <= 0) {
                        $traceInfo = isset($params['traceInfo']) ?
                            $params['traceInfo'] . '->' . $itemsInfo[$i]['item_id'] :
                            $itemsInfo[$i]['item_id'];
                        $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                        $log .= '#' . Yii::$app->user->identity->id . '#加价购#';
                        $log .= '商品ID：' . $traceInfo . ' 数量出错（Count：' . $itemsInfo[$i]['item_number'] . '）';
                        Yii::error($log, __METHOD__);
                        throw new RewriteException('加价购商品数量出错，请联系客服');
                    }
                    if (empty($goodsInfo[$itemsInfo[$i]['item_id']])) {
                        $traceInfo = isset($params['traceInfo']) ?
                            $params['traceInfo'] . '->' . $itemsInfo[$i]['item_id'] :
                            $itemsInfo[$i]['item_id'];
                        $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                        $log .= '#' . Yii::$app->user->identity->id . '#加价购#';
                        $log .= '商品ID：' . $traceInfo . ' ecs_goods中不存在！';
                        Yii::error($log, __METHOD__);
                        throw new RewriteException('加价购中含有不存在的商品，请联系客服');
                    }
                    if ($pid == 0) {
                        if (empty($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra'])) {
                            $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                            $log .= '#' . Yii::$app->user->identity->id . '#加价购#';
                            $log .= '商品ID：' . $itemsInfo[$i]['item_id'] . ' ecs_goods_extra中不存在！';
                            Yii::error($log, __METHOD__);
                            throw new RewriteException('加价购中含有不存在的商品，请联系客服');
                        }
                        if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['on_sale'] != 1) {
                            $errorMessage = '加价购商品【';
                            $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                            $errorMessage .= '】尚未发布';
                            throw new RewriteException($errorMessage);
                        }
                        if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['start_date'] > $timestamp) {
                            $errorMessage = '加价购商品【';
                            $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                            $errorMessage .= '】尚未上架';
                            throw new RewriteException($errorMessage);
                        }
                        if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['end_date'] < $timestamp) {
                            $errorMessage = '加价购商品【';
                            $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                            $errorMessage .= '】已经下架';
                            throw new RewriteException($errorMessage);
                        }

                        // 锁定商品库存
                        $num = Good::updateAllCounters(
                            [
                                'goods_number' => $itemsInfo[$i]['item_number'] * -1,
                                'buy_num' => $itemsInfo[$i]['item_number']
                            ],
                            [
                                'AND',
                                ['=', 'goods_id', $itemsInfo[$i]['item_id']],
                                ['>=', 'goods_number', $itemsInfo[$i]['item_number']]
                            ]
                        );
                        if ($num != 1) {
                            $errorMessage = '抱歉，商品【';
                            $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                            $errorMessage .= '】已售罄';
                            throw new RewriteException($errorMessage);
                        }
                    }

                    // 构建商品详情
                    $itemsInfo[$i] = array_merge(['parent_id' => $pid, 'parent_type' => $pType], $itemsInfo[$i]);
                    $itemsInfo[$i]['item_sn'] = $goodsInfo[$itemsInfo[$i]['item_id']]['goods_sn'];
                    $itemsInfo[$i]['item_name'] = $pid == 0 ?
                        $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'] :
                        (
                            empty($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']) ?
                                $goodsInfo[$itemsInfo[$i]['item_id']]['goods_name'] :
                                $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name']
                        );
                    $itemsInfo[$i]['item_warehouse_id'] = $goodsInfo[$itemsInfo[$i]['item_id']]['warehouse'];
                    $itemsInfo[$i]['item_supplier_id'] = $goodsInfo[$itemsInfo[$i]['item_id']]['supplier'];
                    $itemsInfo[$i]['item_mkt_price'] = $goodsInfo[$itemsInfo[$i]['item_id']]['shop_price'];
                    $itemsInfo[$i]['item_pur_price'] = $pid == 0 ?
                        $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['channel_price'] :
                        (
                            empty($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']) ?
                                $goodsInfo[$itemsInfo[$i]['item_id']]['promote_price'] :
                                $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['channel_price']
                        );
                    if ($pid == 0) {
                        if (isset($itemsInfo[$i]['item_xfer_price'])) {
                            $tmp = $itemsInfo[$i]['item_xfer_price'];
                            unset($itemsInfo[$i]['item_xfer_price']);
                            $itemsInfo[$i]['item_xfer_price'] = $tmp; // 换个数组位置
                        } else {
                            throw new RewriteException('加价购商品价格出错，请联系客服');
                        }
                    } else {
                        $itemsInfo[$i]['item_xfer_price'] = 0;
                    }
                    $itemsInfo[$i]['item_type'] = OrderItem::ITEM_TYPE_NORMAL; // 一般商品
                    if ($goodsInfo[$itemsInfo[$i]['item_id']]['goods_model'] == 1) {
                        $itemsInfo[$i]['item_type'] = OrderItem::ITEM_TYPE_BUNDLE; // 附赠商品
                    } elseif ($goodsInfo[$itemsInfo[$i]['item_id']]['goods_model'] == 2) {
                        $itemsInfo[$i]['item_type'] = OrderItem::ITEM_TYPE_COMBINATION; // 组合商品
                    }
                    $itemsInfo[$i]['item_is_virt'] = $goodsInfo[$itemsInfo[$i]['item_id']]['is_virtual'];
                    if ($itemsInfo[$i]['item_is_virt'] == 1) {
                        // 获取并锁定虚拟码
                        $virtualCodes = Code::findCodes($itemsInfo[$i]['item_sn'], $itemsInfo[$i]['item_number']);
                        $itemsInfo[$i]['virt_code'] = json_encode($virtualCodes);
                    } else {
                        $itemsInfo[$i]['virt_code'] = '';
                    }
                    if ($itemsInfo[$i]['item_type'] > OrderItem::ITEM_TYPE_NORMAL) {
                        // 使用递归处理捆绑商品，首先赋值item_type的地方，所以大于1即可
                        $goodsLink = GoodLink::find()
                            ->select([
                                'item_id' => 'link_goods_id',
                                'item_number' => new Expression(
                                    'goods_number*' . $itemsInfo[$i]['item_number']
                                )
                            ])
                            ->where(['goods_id' => $itemsInfo[$i]['item_id']])
                            ->asArray()
                            ->all();
                        isset($params['traceInfo']) ? // 递归中透传父商品ID信息
                            $params['traceInfo'] .= '->' . $itemsInfo[$i]['item_id'] :
                            $params['traceInfo'] = $itemsInfo[$i]['item_id'];
                        if (empty($goodsLink)) {
                            $traceInfo = $params['traceInfo'];
                            $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                            $log .= '#' . Yii::$app->user->identity->id . '#加价购#';
                            $log .= '商品ID：' . $traceInfo . ' ecs_link_goods未联系商品';
                            Yii::error($log, __METHOD__);
                            throw new RewriteException('加价购中组合商品出错，请联系客服');
                        }
                        $childGoodsInfo = $recurFunc(
                            $goodsLink, $pid + $i + 1, $itemsInfo[$i]['item_type'], $params
                        );
                        array_splice($itemsInfo, $i + 1, 0, $childGoodsInfo);
                        $i += count($childGoodsInfo);
                    } elseif ($goodsInfo[$itemsInfo[$i]['item_id']]['ref_goods_id'] > 0) { // 检查父商品是否捆绑商品
                        $parentGoodModel = Good::getRefGoodModel($goodsInfo[$itemsInfo[$i]['item_id']]['ref_goods_id']);
                        if (!empty($parentGoodModel)) {
                            if ($parentGoodModel['goods_model'] == 1) {
                                $itemsInfo[$i]['item_type'] = OrderItem::ITEM_TYPE_BUNDLE; // 附赠商品
                            } elseif ($parentGoodModel['goods_model'] == 2) {
                                $itemsInfo[$i]['item_type'] = OrderItem::ITEM_TYPE_COMBINATION; // 组合商品
                            }
                            $goodsLink = GoodLink::find()
                                ->select([
                                    'item_id' => 'link_goods_id',
                                    'item_number' => new Expression(
                                        'goods_number*' . $itemsInfo[$i]['item_number']
                                    )
                                ])
                                ->where(['goods_id' => $parentGoodModel['goods_id']])
                                ->asArray()
                                ->all();
                            isset($params['traceInfo']) ? // 递归中透传父商品ID信息
                                $params['traceInfo'] .=
                                    '->' . $itemsInfo[$i]['item_id'] . '(追溯至' . $parentGoodModel['goods_id'] . ')' :
                                $params['traceInfo'] =
                                    $itemsInfo[$i]['item_id'] . '(追溯至' . $parentGoodModel['goods_id'] . ')';
                            if (empty($goodsLink)) {
                                $traceInfo = $params['traceInfo'];
                                $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                                $log .= '#' . Yii::$app->user->identity->id . '#加价购#';
                                $log .= '商品ID：' . $traceInfo . ' ecs_link_goods未联系商品';
                                Yii::error($log, __METHOD__);
                                throw new RewriteException('加价购中组合商品出错，请联系客服');
                            }
                            $childGoodsInfo = $recurFunc(
                                $goodsLink, $pid + $i + 1, $itemsInfo[$i]['item_type'], $params
                            );
                            array_splice($itemsInfo, $i + 1, 0, $childGoodsInfo);
                            $i += count($childGoodsInfo);
                        }
                    }
                }

                return $itemsInfo;
            };

            $additionalItemsInfo = $recurFunc($additionalItemsInfo); // 提取商品订单信息
            if (empty($additionalItemsInfo)) {
                throw new RewriteException('获取加价购商品数据失败，请联系客服');
            }

            // 提取完毕，更新购物车
            UserCart::updateCartByRewriteUserId(['extra_cost_cookie' => ''], $rewriteUserId);

            // 获取送货地址
            $addressInfo = UserAddress::findUserAddressById(Yii::$app->request->post('shippingAddress'));

            // 获取分单号 && 计算优惠
            $separateOrder = $discount = [];
            foreach ($orderItems as $i => $orderItem) {
                if ($orderItem['parent_id'] == 0) {
                    if ($orderItem['item_is_virt'] == 0) { // 0实物商品，按仓库拆单
                        $signKey = $orderItem['item_warehouse_id'];
                    } else { // 1虚拟商品，按仓库和供应商拆单
                        $signKey = $orderItem['item_warehouse_id'] . '_' . $orderItem['item_supplier_id'];
                    }
                    $separateOrder[$signKey] = $orderItem['order_no'];
                }
            }
            $orders = array_combine(array_column($orders, 'order_no'), $orders);
            foreach ($additionalItemsInfo as $key => $value) {
                $pid = $value['parent_id'];
                while ($pid > 0) {
                    $value['item_is_virt'] = $additionalItemsInfo[($pid - 1)]['item_is_virt'];
                    $value['item_warehouse_id'] = $additionalItemsInfo[($pid - 1)]['item_warehouse_id'];
                    $value['item_supplier_id'] = $additionalItemsInfo[($pid - 1)]['item_supplier_id'];
                    $pid = $additionalItemsInfo[($pid - 1)]['parent_id'];
                }
                if ($value['item_is_virt'] == 0) { // 0实物商品，按仓库拆单
                    $signKey = $value['item_warehouse_id'];
                } else { // 1虚拟商品，按仓库和供应商拆单
                    $signKey = $value['item_warehouse_id'] . '_' . $value['item_supplier_id'];
                }
                if (empty($separateOrder[$signKey])) {
                    $orderNo = $separateOrder[$signKey] = Yii::$app
                        ->getModule('channel/v1')
                        ->processor
                        ->getBehavior('order')
                        ->orderNumberGenerate();
                } else {
                    $orderNo = $separateOrder[$signKey];
                }
                $additionalItemsInfo[$key] = array_merge(
                    [
                        'user_id' => Yii::$app->user->identity->id,
                        'order_no' => $orderNo,
                    ],
                    $value
                );
                $additionalItemsInfo[$key]['create_time'] = $datetime;

                // 该项商品费用
                $itemCost = bcmul($value['item_xfer_price'], $value['item_number'], 2);

                // 优惠计算
                if ($value['parent_id'] == 0) {
                    $originalCost = bcmul($value['item_pur_price'], $value['item_number'], 2);
                    $difference = bcsub($originalCost, $itemCost, 2);
                    $discount[$orderNo] = isset($discount[$orderNo]) ?
                        bcadd($discount[$orderNo], $difference, 2) : $difference;
                }

                // 并入订单数据
                if (empty($orders[$orderNo])) {
                    $orders[$orderNo] = [
                        'user_id' => Yii::$app->user->identity->id,
                        'order_no' => $orderNo,
                        'order_amt' => $itemCost,
                        'order_stat' => 0,
                        'create_time' => $datetime
                    ];
                    $orderShipping[] = [
                        'user_id' => Yii::$app->user->identity->id,
                        'consignee' => $addressInfo['consignee'],
                        'phone_no' => $addressInfo['phone_no'],
                        'zip_code' => $addressInfo['zip_code'],
                        'dist_table' => $addressInfo['dist_table'],
                        'dist_id' => $addressInfo['dist_id'],
                        'addr_detail' => $addressInfo['addr_detail'],
                        'message' => Yii::$app->request->post('leavingMessage', ''),
                        'order_no' => $orderNo,
                        'ship_no' => Yii::$app
                            ->getModule('channel/v1')
                            ->processor
                            ->getBehavior('logis')
                            ->logisShippingOrderNumberGenerate(),
                        'ship_stat' => OrderShipping::TO_BE_PAID,
                        'create_time' => $datetime,
                    ];
                } else {
                    $orders[$orderNo]['order_amt'] = bcadd($orders[$orderNo]['order_amt'], $itemCost, 2);
                }
            }

            // 并入订单商品数据
            $offset = count($orderItems);
            foreach ($additionalItemsInfo as $additionalItem) {
                if ($additionalItem['parent_id'] > 0) {
                    $additionalItem['parent_id'] += $offset;
                } else {
                    $additionalItem['parent_id'] = $subordinateRecord[$additionalItem['subordinate_to']] + 1;
                }
                unset($additionalItem['subordinate_to']);
                $orderItems[] = $additionalItem;
            }

            // 订单价格调整记录
            if (!empty($discount)) {
                foreach ($discount as $key => &$value) {
                    $value = [
                        'order_no' => $key,
                        'adjust_type' => OrderAdjustment::TYPE_DISCOUNT,
                        'adjust_name' => '加价购活动优惠',
                        'adjust_detail' => '加价购活动既得优惠总计',
                        'adjust_behavior' => __METHOD__,
                        'pre_adjust_amt' => bcmul($value, -1, 2),
                        'act_adjust_amt' => bcmul($value, -1, 2),
                        'create_time' => $datetime
                    ];
                }
                OrderAdjustment::createRows($discount);
            }
        }
    }
}
