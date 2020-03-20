<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\pay\modules\v1\exceptions\PayException;

class RefundRelation extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%' . Yii::$app->getModule('channel/v1')->processor->channelId . '_refund_relations}}';
    }

    /**
     * 根据退款表ID寻找对应关系
     * @param $refundId
     * @param bool $column
     * @return array|ActiveRecord[]
     * @throws PayException
     */
    public static function findRelationByRefundId($refundId, $column = false)
    {
        if (is_numeric($refundId)) {
            $refundId = [$refundId];
        } elseif (!is_array($refundId)) {
            throw new PayException('传值错误');
        }

        $query = static::find();
        if ($column !== false) {
            $query = $query->select($column);
        }

        $relation = $query->distinct()
            ->where(['IN', 'refund_id', $refundId])
            ->all();

        return $relation;
    }

    /**
     * 根据订单表ID寻找对应关系
     * @param $orderId
     * @param bool $column
     * @return array|ActiveRecord[]
     * @throws PayException
     */
    public static function findRelationByOrderId($orderId, $column = false)
    {
        if (is_numeric($orderId)) {
            $orderId = [$orderId];
        } elseif (!is_array($orderId)) {
            throw new PayException('传值错误');
        }

        $query = static::find();
        if ($column !== false) {
            $query = $query->select($column);
        }

        $relation = $query->distinct()
            ->where(['IN', 'order_id', $orderId])
            ->all();

        return $relation;
    }
}
