<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 商品限购
 * Class PurchaseLimitTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class PurchaseLimitTool extends Behavior
{
    /**
     * 商品限购
     * @param $good
     * @param $cartItemsProperty
     * @throws RewriteException
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        if ($good['goodExtra']['keywords'] > 0) {
            $timestamp = time();
            if ($timestamp >= $good['goodExtra']['limit_start_date']
                && ($good['goodExtra']['limit_end_date'] > 0 ?
                    $timestamp <= $good['goodExtra']['limit_end_date']
                    : true)
            ) {
                $purchasedQty = 0;
                $purchasedQty += $cartItemsProperty[$good['goods_id']][2];
                $query = OrderItem::find()
                    ->alias('orderItem')
                    ->joinWith('order order')
                    ->where([
                        'AND',
                        ['=', 'orderItem.user_id', Yii::$app->user->identity->id],
                        ['=', 'orderItem.item_id', $good['goods_id']],
                        ['=', 'orderItem.parent_id', 0],
                        ['>=', 'order.order_stat', 0]
                    ]);
                if ($good['goodExtra']['limit_start_date'] > 0) {
                    $query = $query->andWhere([
                        '>=',
                        'orderItem.create_time',
                        date('Y-m-d H:i:s', $good['goodExtra']['limit_start_date'])
                    ]);
                }
                if ($good['goodExtra']['limit_end_date'] > 0) {
                    $query = $query->andWhere([
                        '<=',
                        'orderItem.create_time',
                        date('Y-m-d H:i:s', $good['goodExtra']['limit_end_date'])
                    ]);
                }
                $purchasedQty += (int)($query->sum('orderItem.item_number'));
                if ($purchasedQty > $good['goodExtra']['keywords']) {
                    throw new RewriteException(
                        '商品【' . $good['goodExtra']['goods_name'] . '】每人限购'
                        . $good['goodExtra']['keywords'] . '件'
                    );
                }
            }
        }
    }
}
