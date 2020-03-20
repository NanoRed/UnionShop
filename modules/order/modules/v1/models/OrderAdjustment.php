<?php

namespace app\modules\order\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\order\modules\v1\exceptions\OrderException;

class OrderAdjustment extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%' . Yii::$app->getModule('channel/v1')->processor->channelId . '_order_adjustments}}';
    }

    const TYPE_FREIGHT = 1;  // 运费
    const TYPE_DISCOUNT = 2; // 优惠

    /**
     * 记录订单金额调整
     * @param $rows
     * @throws OrderException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount != count($rows)) {
            throw new OrderException('金额调整失败，请重新尝试');
        }
    }
}
