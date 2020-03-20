<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\order\modules\v1\models\Order;
use app\modules\pay\modules\v1\exceptions\PayException;

class Transfer extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_transfer}}';
    }

    public function getOrder()
    {
        return $this->hasMany(Order::className(), ['id' => 'order_id'])
            ->viaTable(TransferRelation::tableName(), ['xfer_id' => 'id']);
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
     * 根据订单号查询数据
     * 返回值会按交易订单ID同序排列（升序）
     * @param mixed $orderNo 订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public static function findRowsByOrderNo($orderNo, $register = true)
    {
        if (is_string($orderNo)) {
            $orderNo = [$orderNo];
        } elseif (!is_array($orderNo)) {
            throw new PayException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['order_no']) || !$register) {
            $newNo = $orderNo;
        } else {
            $newNo = array_diff($orderNo, array_keys(static::$register[static::channelId()]['link']['order_no']));
        }
        if (!empty($newNo)) {
            $orders = Order::findRowsByOrderNo($newNo);
            $relations = TransferRelation::findRelationUnit(
                array_column($orders, 'id'), TransferRelation::TYPE_ORDER_ID
            );
            $xferIds = [];
            $orderIds = [];
            foreach ($relations as $i => $value) {
                $xferIds[$value['xfer_id']][] = $i;
                $orderIds[$value['order_id']][] = $i;
            }
            $relatedTransfers = static::find()
                ->where(['IN', 'id', array_keys($xferIds)])
                ->asArray()
                ->all();
            $relatedOrders = Order::findRowsByOrderId(array_keys($orderIds));
            foreach ($relatedTransfers as $value) {
                static::$register[static::channelId()]['link']['xfer_no'][$value['xfer_no']] = $value['id'];
                static::$register[static::channelId()]['data'][$value['id']] = $value;
            }
            foreach ($relatedOrders as $value) {
                foreach ($orderIds[$value['id']] as $i) {
                    $corrXferId = $relations[$i]['xfer_id'];
                    static::$register[static::channelId()]['link']['order_no'][$value['order_no']][$corrXferId] = '';
                    $corrXferNo = static::$register[static::channelId()]['data'][$corrXferId]['xfer_no'];
                    static::$register[static::channelId()]['unit']['order_no'][$value['order_no']][$corrXferNo]
                        = $corrXferId;
                    static::$register[static::channelId()]['unit']['xfer_no'][$corrXferNo][$value['order_no']]
                        = $value['id'];
                }
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
     * 根据交易订单号查询数据
     * 返回值会按交易订单ID同序排列（升序）
     * @param mixed $xferNo 支付订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public static function findRowsByXferNo($xferNo, $register = true)
    {
        if (is_string($xferNo)) {
            $xferNo = [$xferNo];
        } elseif (!is_array($xferNo)) {
            throw new PayException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['xfer_no']) || !$register) {
            $newNo = $xferNo;
        } else {
            $newNo = array_diff($xferNo, array_keys(static::$register[static::channelId()]['link']['xfer_no']));
        }
        if (!empty($newNo)) {
            $xferId = static::find()
                ->select('id')
                ->where(['IN', 'xfer_no', $newNo])
                ->column();
            $relations = TransferRelation::findRelationUnit($xferId, TransferRelation::TYPE_XFER_ID);
            $xferIds = [];
            $orderIds = [];
            foreach ($relations as $i => $value) {
                $xferIds[$value['xfer_id']][] = $i;
                $orderIds[$value['order_id']][] = $i;
            }
            $relatedTransfers = static::find()
                ->where(['IN', 'id', array_keys($xferIds)])
                ->asArray()
                ->all();
            $relatedOrders = Order::findRowsByOrderId(array_keys($orderIds));
            foreach ($relatedTransfers as $value) {
                static::$register[static::channelId()]['link']['xfer_no'][$value['xfer_no']] = $value['id'];
                static::$register[static::channelId()]['data'][$value['id']] = $value;
            }
            foreach ($relatedOrders as $value) {
                foreach ($orderIds[$value['id']] as $i) {
                    $corrXferId = $relations[$i]['xfer_id'];
                    static::$register[static::channelId()]['link']['order_no'][$value['order_no']][$corrXferId] = '';
                    $corrXferNo = static::$register[static::channelId()]['data'][$corrXferId]['xfer_no'];
                    static::$register[static::channelId()]['unit']['order_no'][$value['order_no']][$corrXferNo]
                        = $corrXferId;
                    static::$register[static::channelId()]['unit']['xfer_no'][$corrXferNo][$value['order_no']]
                        = $value['id'];
                }
            }
        }
        $result = [];
        foreach ($xferNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['xfer_no'][$no])
                && isset(
                    static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['xfer_no'][$no]
                    ]
                )
            ) {
                $result[static::$register[static::channelId()]['link']['xfer_no'][$no]]
                    = static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['xfer_no'][$no]
                    ];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 根据退款交易订单号查询数据
     * 返回值会按交易订单ID同序排列（升序）
     * 注意：此查询不会追溯出完整的TransferRelations单元数据
     * @param mixed $refundNo 退款订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     */
    public static function findRowsByRefundNo($refundNo, $register = true)
    {
        if (is_string($refundNo)) {
            $refundNo = [$refundNo];
        } elseif (!is_array($refundNo)) {
            throw new PayException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['refund_no']) || !$register) {
            $newNo = $refundNo;
        } else {
            $newNo = array_diff($refundNo, array_keys(static::$register[static::channelId()]['link']['refund_no']));
        }
        if (!empty($newNo)) {
            $refunds = Refund::findRowsByRefundNo($newNo);
            $relations = RefundRelation::findRelationByRefundId(
                array_column($refunds, 'id'), ['refund_id', 'xfer_id']
            );
            $relations = array_column($relations, 'xfer_id', 'refund_id'); // 不会一退款对应多支付
            $transfers = static::find()
                ->where(['IN', 'id', array_unique($relations)])
                ->indexBy('id')
                ->asArray()
                ->all();
            foreach ($refunds as $value) {
                static::$register[static::channelId()]['link']['refund_no'][$value['refund_no']]
                    = $relations[$value['id']];
                static::$register[static::channelId()]['data'][$relations[$value['id']]]
                    = $transfers[$relations[$value['id']]];
            }
        }
        $result = [];
        foreach ($refundNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['refund_no'][$no])
                && isset(
                    static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['refund_no'][$no]
                    ]
                )
            ) {
                $result[static::$register[static::channelId()]['link']['refund_no'][$no]]
                    = static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['refund_no'][$no]
                    ];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 返回订单号关系的所有交易订单号
     * 返回值会按交易订单ID同序排列（升序）
     * @param string $orderNo 订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public static function findRelatedXferNoByOrderNo($orderNo, $register = true)
    {
        if (empty(static::$register[static::channelId()]['unit']['order_no'][$orderNo]) || !$register) {
            $orders = Order::findRowsByOrderNo($orderNo);
            $relations = TransferRelation::findRelationUnit(
                array_column($orders, 'id'), TransferRelation::TYPE_ORDER_ID
            );
            $xferIds = [];
            $orderIds = [];
            foreach ($relations as $i => $value) {
                $xferIds[$value['xfer_id']][] = $i;
                $orderIds[$value['order_id']][] = $i;
            }
            $relatedTransfers = static::find()
                ->where(['IN', 'id', array_keys($xferIds)])
                ->asArray()
                ->all();
            $relatedOrders = Order::findRowsByOrderId(array_keys($orderIds));
            foreach ($relatedTransfers as $value) {
                static::$register[static::channelId()]['link']['xfer_no'][$value['xfer_no']] = $value['id'];
                static::$register[static::channelId()]['data'][$value['id']] = $value;
            }
            foreach ($relatedOrders as $value) {
                foreach ($orderIds[$value['id']] as $i) {
                    $corrXferId = $relations[$i]['xfer_id'];
                    static::$register[static::channelId()]['link']['order_no'][$value['order_no']][$corrXferId] = '';
                    $corrXferNo = static::$register[static::channelId()]['data'][$corrXferId]['xfer_no'];
                    static::$register[static::channelId()]['unit']['order_no'][$value['order_no']][$corrXferNo]
                        = $corrXferId;
                    static::$register[static::channelId()]['unit']['xfer_no'][$corrXferNo][$value['order_no']]
                        = $value['id'];
                }
            }
        }
        $xferNo = [];
        foreach (static::$register[static::channelId()]['unit']['order_no'][$orderNo] as $key => $value) {
            $xferNo[$value] = $key;
        }
        ksort($xferNo);
        $xferNo = array_values($xferNo);

        return $xferNo;
    }

    /**
     * 返回交易订单号关系的所有订单号
     * 返回值会按订单ID同序排列（升序）
     * @param string $xferNo 支付订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public static function findRelatedOrderNoByXferNo($xferNo, $register = true)
    {
        if (empty(static::$register[static::channelId()]['unit']['xfer_no'][$xferNo]) || !$register) {
            $xferId = static::find()
                ->select('id')
                ->where(['xfer_no' => $xferNo])
                ->scalar();
            $relations = TransferRelation::findRelationUnit($xferId, TransferRelation::TYPE_XFER_ID);
            $xferIds = [];
            $orderIds = [];
            foreach ($relations as $i => $value) {
                $xferIds[$value['xfer_id']][] = $i;
                $orderIds[$value['order_id']][] = $i;
            }
            $relatedTransfers = static::find()
                ->where(['IN', 'id', array_keys($xferIds)])
                ->asArray()
                ->all();
            $relatedOrders = Order::findRowsByOrderId(array_keys($orderIds));
            foreach ($relatedTransfers as $value) {
                static::$register[static::channelId()]['link']['xfer_no'][$value['xfer_no']] = $value['id'];
                static::$register[static::channelId()]['data'][$value['id']] = $value;
            }
            foreach ($relatedOrders as $value) {
                foreach ($orderIds[$value['id']] as $i) {
                    $corrXferId = $relations[$i]['xfer_id'];
                    static::$register[static::channelId()]['link']['order_no'][$value['order_no']][$corrXferId] = '';
                    $corrXferNo = static::$register[static::channelId()]['data'][$corrXferId]['xfer_no'];
                    static::$register[static::channelId()]['unit']['order_no'][$value['order_no']][$corrXferNo]
                        = $corrXferId;
                    static::$register[static::channelId()]['unit']['xfer_no'][$corrXferNo][$value['order_no']]
                        = $value['id'];
                }
            }
        }
        $orderNo = [];
        foreach (static::$register[static::channelId()]['unit']['xfer_no'][$xferNo] as $key => $value) {
            $orderNo[$value] = $key;
        }
        ksort($orderNo);
        $orderNo = array_values($orderNo);

        return $orderNo;
    }

    /**
     * 插入支付交易订单
     * @param $row
     * @throws PayException
     * @throws \yii\db\Exception
     */
    public static function createRow($row)
    {
        if (strlen($row['xfer_no']) > 20) {
            throw new PayException('交易订单号异常');
        }
        $rowCount = Yii::$app->db->createCommand()
            ->insert(static::tableName(), $row)
            ->execute();
        if ($rowCount != 1) {
            throw new PayException('交易订单创建失败，请重新尝试');
        }
    }

    const TO_BE_PAID = 0; // 等待支付
    const PAID = 1;       // 支付成功

    /**
     * 更新交易订单状态
     * @param $xferNo
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     * @throws \Exception
     */
    public static function updateStatByXferNo($xferNo, $stat, array $andWhere = [], array $params = [])
    {
        if (is_array($xferNo)) {
            $xferNo = array_filter($xferNo);
        } elseif (is_string($xferNo)) {
            $xferNo = [$xferNo];
        }
        $condition = ['IN', 'xfer_no', $xferNo];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $params['xfer_stat'] = $stat;
        if ($stat == static::PAID && empty($params['xfer_ntime'])) {
            $params['xfer_ntime'] = date('Y-m-d H:i:s');
        }
        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($xferNo)) {
            if (isset(static::$register[static::channelId()]['data'])) {
                foreach (static::$register[static::channelId()]['data'] as &$value) {
                    if (in_array($value['xfer_no'], $xferNo)) {
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
