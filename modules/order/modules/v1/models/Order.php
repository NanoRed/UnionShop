<?php

namespace app\modules\order\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\pay\modules\v1\models\TransferRelation;
use app\modules\pay\modules\v1\models\Refund;
use app\modules\pay\modules\v1\models\RefundRelation;
use app\modules\logis\modules\v1\models\OrderShipping;
use app\modules\order\modules\v1\exceptions\OrderException;

class Order extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_orders}}';
    }

    public function getOrderItem()
    {
        return $this->hasMany(OrderItem::className(), ['order_no' => 'order_no']);
    }

    public function getLatestTransfer()
    {
        return $this->hasOne(Transfer::className(), ['id' => 'xfer_id'])
            ->viaTable(TransferRelation::tableName(), ['order_id' => 'id'], function ($query) {
                $query->orderBy(['id' => SORT_DESC])->limit(1);
            });
    }

    public function getOrderShipping()
    {
        return $this->hasOne(OrderShipping::className(), ['order_no' => 'order_no']);
    }

    public static $register; // 寄存器

    /**
     * 释放寄存器
     */
    public static function clearRegister()
    {
        static::$register = null;
    }

    /**
     * 根据订单ID查询数据
     * @param mixed $orderId 订单ID
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws OrderException
     */
    public static function findRowsByOrderId($orderId, $register = true)
    {
        if (is_numeric($orderId)) {
            $orderId = [$orderId];
        } elseif (!is_array($orderId)) {
            throw new OrderException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['data']) || !$register) {
            $newId = $orderId;
        } else {
            $newId = array_diff($orderId, array_keys(static::$register[static::channelId()]['data']));
        }
        if (!empty($newId)) {
            $data = static::find()
                ->where(['IN', 'id', $newId])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register[static::channelId()]['link']['order_no'][$row['order_no']] = $row['id'];
                static::$register[static::channelId()]['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($orderId as $id) {
            if (isset(static::$register[static::channelId()]['data'][$id])) {
                $result[$id] = static::$register[static::channelId()]['data'][$id];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 根据订单号查询数据
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
            foreach ($data as $row) {
                static::$register[static::channelId()]['link']['order_no'][$row['order_no']] = $row['id'];
                static::$register[static::channelId()]['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($orderNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['order_no'][$no])
                && isset(
                    static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['order_no'][$no]
                    ]
                )
            ) {
                $result[static::$register[static::channelId()]['link']['order_no'][$no]]
                    = static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['order_no'][$no]
                    ];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 根据退款订单号查询数据
     * @param mixed $refundNo 退款订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws OrderException
     * @throws \app\modules\pay\modules\v1\exceptions\PayException
     */
    public static function findRowsByRefundNo($refundNo, $register = true)
    {
        if (is_string($refundNo)) {
            $refundNo = [$refundNo];
        } elseif (!is_array($refundNo)) {
            throw new OrderException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['refund_no']) || !$register) {
            $newNo = $refundNo;
        } else {
            $newNo = array_diff($refundNo, array_keys(static::$register[static::channelId()]['link']['refund_no']));
        }
        if (!empty($newNo)) {
            $refunds = Refund::findRowsByRefundNo($newNo);
            $relations = RefundRelation::findRelationByRefundId(
                array_column($refunds, 'id'), ['refund_id', 'order_id']
            );
            $map = [];
            foreach ($relations as $i => $value) {
                $map[$value['refund_id']][$value['order_id']] = '';
            }
            $data = static::find()
                ->where(['IN', 'id', array_unique(array_column($relations, 'order_id'))])
                ->asArray()
                ->all();
            foreach ($refunds as $value) {
                static::$register[static::channelId()]['link']['refund_no'][$value['refund_no']] = $map[$value['id']];
            }
            foreach ($data as $value) {
                static::$register[static::channelId()]['data'][$value['id']] = $value;
            }
        }
        $result = [];
        foreach ($refundNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['refund_no'][$no])) {
                foreach (static::$register[static::channelId()]['link']['refund_no'][$no] as $key => $value) {
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
     * 插入订单
     * @param $rows
     * @throws OrderException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        array_map(function ($value) {
            if (strlen($value['order_no']) > 20) {
                throw new OrderException('订单号异常');
            }
        }, $rows);
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount != count($rows)) {
            throw new OrderException('订单创建异常，请重新尝试');
        }
    }

    const REFUNDED = -2;  // 已退款（全额或部分）
    const CANCELED = -1;  // 订单已取消（释放库存）
    const TO_BE_PAID = 0; // 等待支付
    const PAID = 1;       // 已支付
    const COMPLETE = 2;   // 已完成

    /**
     * 更新订单状态
     * @param $orderNo
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     */
    public static function updateStatByOrderNo($orderNo, $stat, array $andWhere = [], array $params = [])
    {
        if (is_array($orderNo)) {
            $orderNo = array_filter($orderNo);
        } elseif (is_string($orderNo)) {
            $orderNo = [$orderNo];
        }
        $condition = ['IN', 'order_no', $orderNo];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $params['order_stat'] = $stat;
        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($orderNo)) {
            if (isset(static::$register[static::channelId()]['data'])) {
                foreach (static::$register[static::channelId()]['data'] as &$value) {
                    if (in_array($value['order_no'], $orderNo)) {
                        $value = array_merge($value, $params);
                    }
                }
            }
        } elseif ($rowCount > 0) {
            static::clearRegister();
        }

        return $rowCount;
    }
}
