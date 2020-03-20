<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\Expression;
use app\modules\rewrite\modules\v1\models\UserCart;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\Good;
use app\modules\rewrite\modules\v1\models\GoodLink;
use app\modules\rewrite\modules\v1\models\Code;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 待重写系统商品盒
 * Class CartItemBox
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class CartItemBox extends Behavior
{
    /**
     * 读出购物车商品信息
     * 确保此方法执行在最低隔离级别（READ_UNCOMMITTED）事务内
     * @return array|mixed
     * @throws RewriteException
     */
    public function getOrderItems()
    {
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new RewriteException('非法执行');
        }

        $timestamp = time();
        $owner = $this->owner;
        $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);
        $cartInfo = UserCart::findCartItems($rewriteUserId);

        // 获取购物车商品
        $cartItemsProperty = $itemsInfo = [];
        if (!empty($cartInfo['checked'])) {
            $itemCodes = array_filter(explode(',', $cartInfo['checked']));
            foreach ($itemCodes as $itemCode) {
                // 1516（商品ID）-0（这个是？不知道）-1（数量）-510519（邀请码）-C1102023V1J1Y（特权码）
                $itemProperty = explode('-', $itemCode);
                $itemProperty[0] = (int)$itemProperty[0]; // itemId
                $itemProperty[2] = (int)$itemProperty[2]; // itemQty
                $cartItemsProperty[$itemProperty[0]] = $itemProperty;
                $itemsInfo[] = [
                    'item_id' => $itemProperty[0],
                    'item_number' => $itemProperty[2],
                ];
            }
        }
        if (empty($itemsInfo)) {
            throw new RewriteException('购物车为空，请添加商品');
        }

        // 获取商品信息，这是个递归闭包函数，原因是用于处理类组合商品
        $recurFunc = function ($itemsInfo, $pid = 0, $pType = 0, $params = []) use (
            &$recurFunc, $owner, $timestamp, $cartItemsProperty
        ) {
            $goodsInfo = Good::find()
                ->select([
                    'goods_id',        'goods_sn',      'goods_name',
                    'warehouse',       'supplier',      'shop_price',
                    'promote_price',   'is_invite',     'is_privilege',
                    'is_virtual',      'goods_model',   'sale_region',
                    'ref_goods_id',
                ])
                ->where(['IN', 'goods_id', array_column($itemsInfo, 'item_id')])
                ->indexBy('goods_id')
                ->with([
                    'goodExtra' => function ($query) {
                        $query->select([
                            'goods_id',     'goods_name',         'channel_price',
                            'start_date',   'end_date',           'on_sale',
                            'keywords',     'limit_start_date',   'limit_end_date',
                            'is_seckill',   'is_reward',
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
                    $log .= '#' . Yii::$app->user->identity->id . '#';
                    $log .= '商品ID：' . $traceInfo . ' 数量出错（Count：' . $itemsInfo[$i]['item_number'] . '）';
                    Yii::error($log, __METHOD__);
                    throw new RewriteException('商品数量出错，请联系客服');
                }
                if (empty($goodsInfo[$itemsInfo[$i]['item_id']])) {
                    $traceInfo = isset($params['traceInfo']) ?
                        $params['traceInfo'] . '->' . $itemsInfo[$i]['item_id'] :
                        $itemsInfo[$i]['item_id'];
                    $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                    $log .= '#' . Yii::$app->user->identity->id . '#';
                    $log .= '商品ID：' . $traceInfo . ' ecs_goods中不存在！';
                    Yii::error($log, __METHOD__);
                    throw new RewriteException('含有不存在的商品，请联系客服');
                }
                if ($pid == 0) {
                    if (empty($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra'])) {
                        $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                        $log .= '#' . Yii::$app->user->identity->id . '#';
                        $log .= '商品ID：' . $itemsInfo[$i]['item_id'] . ' ecs_goods_extra中不存在！';
                        Yii::error($log, __METHOD__);
                        throw new RewriteException('含有不存在的商品，请联系客服');
                    }
                    if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['on_sale'] != 1) {
                        $errorMessage = '商品【';
                        $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                        $errorMessage .= '】尚未发布';
                        throw new RewriteException($errorMessage);
                    }
                    if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['start_date'] > $timestamp) {
                        $errorMessage = '商品【';
                        $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                        $errorMessage .= '】尚未上架';
                        throw new RewriteException($errorMessage);
                    }
                    if ($goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['end_date'] < $timestamp) {
                        $errorMessage = '商品【';
                        $errorMessage .= $goodsInfo[$itemsInfo[$i]['item_id']]['goodExtra']['goods_name'];
                        $errorMessage .= '】已经下架';
                        throw new RewriteException($errorMessage);
                    }

                    // 功能检查
                    if ($owner->hasMethod('marketCartItemValidate')) {
                        $owner->marketCartItemValidate($goodsInfo[$itemsInfo[$i]['item_id']], $cartItemsProperty);
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
                $itemsInfo[$i]['item_xfer_price'] = $pid == 0 ? $itemsInfo[$i]['item_pur_price'] : 0;
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
                            'item_number' => new Expression('goods_number*' . $itemsInfo[$i]['item_number'])
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
                        $log .= '#' . Yii::$app->user->identity->id . '#';
                        $log .= '商品ID：' . $traceInfo . ' ecs_link_goods未联系商品';
                        Yii::error($log, __METHOD__);
                        throw new RewriteException('组合商品出错，请联系客服');
                    }
                    $childGoodsInfo = $recurFunc($goodsLink, $pid + $i + 1, $itemsInfo[$i]['item_type'], $params);
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
                            $params['traceInfo']
                                .= '->' . $itemsInfo[$i]['item_id'] . '(追溯至' . $parentGoodModel['goods_id'] . ')' :
                            $params['traceInfo']
                                = $itemsInfo[$i]['item_id'] . '(追溯至' . $parentGoodModel['goods_id'] . ')';
                        if (empty($goodsLink)) {
                            $traceInfo = $params['traceInfo'];
                            $log = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                            $log .= '#' . Yii::$app->user->identity->id . '#';
                            $log .= '商品ID：' . $traceInfo . ' ecs_link_goods未联系商品';
                            Yii::error($log, __METHOD__);
                            throw new RewriteException('组合商品出错，请联系客服');
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

        $itemsInfo = $recurFunc($itemsInfo);
        if (empty($itemsInfo)) {
            throw new RewriteException('获取购物车数据失败，请联系客服');
        }

        // 提取完毕，更新购物车
        $cookieCodes = array_filter(explode(',', $cartInfo['cookie']));
        $cookieCodesNew = [];
        foreach ($cookieCodes as $cookieCode) {
            list($cookieItemId,) = explode('-', $cookieCode, 2);
            if (!isset($cartItemsProperty[$cookieItemId])) {
                $cookieCodesNew[] = $cookieCode;
            }
        }
        $cookieCodesNew = implode(',', $cookieCodesNew);
        UserCart::updateCartByRewriteUserId(['cookie' => $cookieCodesNew, 'checked' => ''], $rewriteUserId);

        return $itemsInfo;
    }

    /**
     * 订单商品恢复库存
     * 确保此方法执行在最低隔离级别（READ_UNCOMMITTED）事务内
     * @param $orderNo
     * @throws RewriteException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function restoreOrderItems($orderNo)
    {
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new RewriteException('非法执行');
        }

        $orderItems = OrderItem::findRowsByOrderNo($orderNo);
        $itemCount = [];
        foreach ($orderItems as $value) {
            if ($value['parent_id'] == 0) {
                isset($itemCount[$value['item_id']]) ?
                    $itemCount[$value['item_id']] += $value['item_number'] :
                    $itemCount[$value['item_id']] = $value['item_number'];
            }
        }
        foreach ($itemCount as $itemId => $itemNumber) {
            $num = Good::updateAllCounters(
                ['goods_number' => $itemNumber, 'buy_num' => $itemNumber * -1],
                ['goods_id' => $itemId]
            );
            if ($num != 1) {
                throw new RewriteException('抱歉，恢复商品库存异常');
            }
        }
    }
}
