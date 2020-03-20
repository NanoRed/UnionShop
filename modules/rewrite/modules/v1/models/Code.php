<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\order\modules\v1\exceptions\OrderException;

class Code extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_code}}';
    }

    /**
     * 获取并锁定特定数量虚拟码
     * @param $itemSn
     * @param $itemQty
     * @return array
     * @throws OrderException
     * @throws \yii\db\Exception
     */
    public static function findCodes($itemSn, $itemQty)
    {
        $codes = [];
        $tableName = static::tableName();
        $paramPrefix = '@' . md5($tableName . __METHOD__) . '_';
        $virtualCode = $paramPrefix . 'virtual_code';
        $updateSQL = "UPDATE {$tableName}
                SET `isuse` = 1, `code` = {$virtualCode} := `code`
                WHERE `goods_sn` = :itemSn AND `isuse` = 0 LIMIT 1";
        $selectSQL = "SELECT {$virtualCode}";
        for ($i = 0; $i < $itemQty; $i++) {
            $rowCount = static::getDb()->createCommand($updateSQL, [':itemSn' => $itemSn])->execute();
            if ($rowCount == 1) {
                $codes[] = static::getDb()->createCommand($selectSQL)->queryScalar();
            } else {
                throw new OrderException('抱歉，此商品已售罄');
            }
        }

        return $codes;
    }
}
