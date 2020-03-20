<?php

namespace app\modules\order\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\order\modules\v1\exceptions\OrderException;

class OrderItem extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_order_items}}';
    }

    public function getOrder()
    {
        return $this->hasOne(Order::className(), ['order_no' => 'order_no']);
    }

    const ITEM_TYPE_NORMAL = 1;              // 普通商品   二进制为1
    const ITEM_TYPE_BUNDLE = 2;              // 附赠商品   二进制为10
    const ITEM_TYPE_COMBINATION = 4;         // 组合商品   二进制为100
    const ITEM_TYPE_ADDITIONAL_PURCHASE = 8; // 加价购商品 二进制为1000

    public static $register; // 寄存器

    /**
     * 释放寄存器
     */
    public static function clearRegister()
    {
        static::$register = null;
    }

    /**
     * 根据订单号查询数据
     * 返回值会按订单商品行ID同序排列（升序）
     * @param mixed $orderNo 订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws OrderException
     */
    public static function findRowsByOrderNo($orderNo, $register = true)
    {
        if (is_string($orderNo)) {
            $orderNo = [$orderNo];
        } elseif (!is_array($orderNo)) {
            throw new OrderException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['order_no']) || !$register) {
            $newNo = $orderNo;
        } else {
            $newNo = array_diff($orderNo, array_keys(static::$register[static::channelId()]['link']['order_no']));
        }
        if (!empty($newNo)) {
            $data = static::find()
                ->where(['IN', 'order_no', $newNo])
                ->asArray()
                ->all();
            $f = true;
            foreach ($data as $row) {
                if ($f) {
                    unset(static::$register[static::channelId()]['link']['order_no'][$row['order_no']]);
                    $f = false;
                }
                static::$register[static::channelId()]['link']['order_no'][$row['order_no']][$row['id']] = '';
                static::$register[static::channelId()]['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($orderNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['order_no'][$no])) {
                foreach (static::$register[static::channelId()]['link']['order_no'][$no] as $key => $value) {
                    if (isset(static::$register[static::channelId()]['data'][$key])) {
                        $result[$key] = static::$register[static::channelId()]['data'][$key];
                    }
                }
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 插入订单详情
     * @param $rows
     * @throws OrderException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount == count($rows)) {
            $orderNo = array_unique(array_column($rows, 'order_no'));
            $findOrderItems = static::find()
                ->where(['IN', 'order_no', $orderNo])
                ->indexBy('id')
                ->asArray()
                ->all();
            $orderItemMap = [];
            foreach ($findOrderItems as $key => $value) {
                $orderItemMap[$value['order_no'] . '_' . $value['parent_id'] . '_' . $value['item_id']] = $key;
            }
            foreach ($rows as $key => $value) {
                if ($value['parent_id'] > 0) {
                    $mapKey = $value['order_no'] . '_' . $value['parent_id'] . '_' . $value['item_id'];
                    $parent = $rows[($value['parent_id'] - 1)];
                    $parentMapKey = $parent['order_no'] . '_' . $parent['parent_id'] . '_' . $parent['item_id'];
                    $findOrderItems[$orderItemMap[$mapKey]]['parent_id'] = $orderItemMap[$parentMapKey];
                }
            }
            if (isset($parent)) {
                $updateRows = array_filter(
                    $findOrderItems,
                    function ($v) {
                        return $v['parent_id'] > 0;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                $updateSql = Yii::$app->db->createCommand()
                        ->batchInsert(
                            static::tableName(),
                            array_keys(reset($findOrderItems)),
                            $updateRows
                        )
                        ->getSql() . ' ON DUPLICATE KEY UPDATE `parent_id` = VALUES(`parent_id`)';
                // 一般商品占比大，暂不考虑ON DUPLICATE KEY UPDATE造成的主键倍增问题
                $n = Yii::$app->db->createCommand($updateSql)->execute();
                if ($n <= 0) {
                    throw new OrderException('订单详情更新异常，请重新尝试');
                }
            }
        } else {
            throw new OrderException('订单详情创建异常，请重新尝试');
        }
    }
}
