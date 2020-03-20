<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\pay\modules\v1\exceptions\PayException;

class TransferRelation extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%' . Yii::$app->getModule('channel/v1')->processor->channelId . '_transfer_relations}}';
    }

    /**
     * 插入订单关联
     * @param $rows
     * @throws PayException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount != count($rows)) {
            throw new PayException('订单关联异常，请重新尝试');
        }
    }

    const TYPE_ORDER_ID = -1; // 订单ID类型
    const TYPE_XFER_ID = 1;   // 支付订单ID类型

    /**
     * 根据既有ID查询完整的多对多关系
     * @param $anyIds
     * @param $type
     * @return array
     * @throws PayException
     */
    public static function findRelationUnit($anyIds, $type)
    {
        $tableName = static::tableName();
        $paramPrefix = '@' . md5($tableName . __METHOD__) . '_';
        $rowId = $paramPrefix . 'auto_id';
        $condId = $paramPrefix . 'condition_ids';
        $findId = $paramPrefix . 'field_ids';
        $toggle = $paramPrefix . 'toggle';
        $sign = $paramPrefix . 'sign';
        $calSign = $paramPrefix . 'cal_sign';
        $sql = "SELECT 
                    {$rowId} := {$rowId} + 1 AS id,
                    {$findId} := {$condId} AS ids_1, 
                    (
                        SELECT {$condId} := GROUP_CONCAT(IF({$toggle}=-1, `xfer_id`, `order_id`)) AS ids 
                        FROM {$tableName} 
                        WHERE FIND_IN_SET(IF({$toggle}=1, `xfer_id`, `order_id`), {$condId})
                        LIMIT 1
                    ) AS ids_2,
                    {$toggle} := -{$toggle} AS toggle, 
                    {$sign} := {$calSign} AS sign, 
                    {$calSign} := IF(
                        {$toggle}=1, 
                        CONCAT({$findId}, ',', {$condId}), 
                        CONCAT({$condId}, ',', {$findId})
                    ) AS calSign 
                FROM 
                    (
                        SELECT 
                            {$rowId} := 0, 
                            {$findId} := '', 
                            {$condId} := :idsStr, 
                            {$toggle} := :toggle, 
                            {$sign} := '+', 
                            {$calSign} := '-'
                    ) vars, 
                    {$tableName} 
                WHERE {$sign} != {$calSign} 
                ORDER BY id DESC
                LIMIT 1";
        if (is_numeric($anyIds)) {
            $anyIds = [$anyIds];
        } elseif (!is_array($anyIds)) {
            throw new PayException('传值错误');
        }
        $data = static::findBySql($sql, [':idsStr' => implode(',', $anyIds), ':toggle' => $type])
            ->asArray()
            ->one();
        if ($data['toggle'] == static::TYPE_ORDER_ID) {
            $xferId = explode(',', $data['ids_1']);
            $orderId = explode(',', $data['ids_2']);
        } elseif ($data['toggle'] == static::TYPE_XFER_ID) {
            $orderId = explode(',', $data['ids_1']);
            $xferId = explode(',', $data['ids_2']);
        } else {
            throw new PayException('类型错误');
        }
        $result = [];
        foreach ($xferId as $i => $value) {
            $result[] = [
                'xfer_id' => $value,
                'order_id' => $orderId[$i]
            ];
        }
        return $result;
    }
}
