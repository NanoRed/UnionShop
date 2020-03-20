<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class Good extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_goods}}';
    }

    public function getGoodExtra()
    {
        $channelAlias = strtolower(Yii::$app->getModule('channel/v1')->processor->channelAlias);
        return $this
            ->hasOne(GoodExtra::className(), ['goods_id' => 'goods_id'])
            ->where(['wx_client_code' => $channelAlias]);
    }

    /**
     * 追溯商品的最就近的商品模式为捆绑商品的父商品
     * @param $goodsId
     * @return mixed
     */
    public static function getRefGoodModel($goodsId)
    {
        $tableName = static::tableName();
        $paramPrefix = '@' . md5($tableName . __METHOD__) . '_';
        $id = $paramPrefix . 'goods_id';
        $sql = "SELECT t1.goods_id, t1.goods_model
            FROM (
                SELECT
                    {$id} AS goods_id,
                    (
                        SELECT {$id} := ref_goods_id AS ref_goods_id
                        FROM {$tableName}
                        WHERE goods_id = {$id}
                        LIMIT 1
                    ) AS ref_goods_id
                FROM
                    (SELECT {$id} := :goodsId) vars, {$tableName}
                WHERE {$id} > 0
            ) t2
            JOIN {$tableName} t1
            ON t1.goods_id = t2.goods_id
            WHERE t1.goods_model IN (1, 2)
            LIMIT 1";
        return static::findBySql($sql, [':goodsId' => $goodsId])->asArray()->one();
    }
}
