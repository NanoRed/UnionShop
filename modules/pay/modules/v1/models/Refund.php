<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\pay\modules\v1\exceptions\PayException;

class Refund extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_refunds}}';
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
     * 根据退款订单ID查询数据
     * 返回值会按退款订单ID同序排列（升序）
     * @param mixed $refundId 退款订单ID
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws PayException
     */
    public static function findRowsByRefundId($refundId, $register = true)
    {
        if (is_numeric($refundId)) {
            $refundId = [$refundId];
        } elseif (!is_array($refundId)) {
            throw new PayException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['data']) || !$register) {
            $newId = $refundId;
        } else {
            $newId = array_diff($refundId, array_keys(static::$register[static::channelId()]['data']));
        }
        if (!empty($newId)) {
            $data = static::find()
                ->where(['IN', 'id', $newId])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register[static::channelId()]['link']['refund_no'][$row['refund_no']] = $row['id'];
                static::$register[static::channelId()]['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($refundId as $id) {
            if (isset(static::$register[static::channelId()]['data'][$id])) {
                $result[$id] = static::$register[static::channelId()]['data'][$id];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 根据退款订单号查询数据
     * 返回值会按退款订单ID同序排列（升序）
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
            $data = static::find()
                ->where(['IN', 'refund_no', $newNo])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register[static::channelId()]['link']['refund_no'][$row['refund_no']] = $row['id'];
                static::$register[static::channelId()]['data'][$row['id']] = $row;
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
     * 插入退款订单
     * @param $row
     * @throws PayException
     * @throws \yii\db\Exception
     */
    public static function createRow($row)
    {
        if (strlen($row['refund_no']) > 20) {
            throw new PayException('退款订单号异常');
        }
        $rowCount = Yii::$app->db->createCommand()
            ->insert(static::tableName(), $row)
            ->execute();
        if ($rowCount != 1) {
            throw new PayException('退款订单创建失败，请重新尝试');
        }
    }

    const CANCELED = -1;    // 退款取消
    const UNDER_REVIEW = 0; // 退款审核中
    const REFUNDED = 1;     // 成功退款

    /**
     * 更新退款订单状态
     * @param $refundNo
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     * @throws \Exception
     */
    public static function updateStatByRefundNo($refundNo, $stat, array $andWhere = [], array $params = [])
    {
        if (is_array($refundNo)) {
            $refundNo = array_filter($refundNo);
        } elseif (is_string($refundNo)) {
            $refundNo = [$refundNo];
        }
        $condition = ['IN', 'refund_no', $refundNo];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $params['refund_stat'] = $stat;
        if ($stat == static::REFUNDED && empty($params['refund_ntime'])) {
            $params['refund_ntime'] = date('Y-m-d H:i:s');
        }
        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($refundNo)) {
            if (isset(static::$register[static::channelId()]['data'])) {
                foreach (static::$register[static::channelId()]['data'] as &$value) {
                    if (in_array($value['refund_no'], $refundNo)) {
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
