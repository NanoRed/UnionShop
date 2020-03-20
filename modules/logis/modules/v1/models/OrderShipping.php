<?php

namespace app\modules\logis\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\logis\modules\v1\exceptions\LogisException;

class OrderShipping extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_order_shipping}}';
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
     * 返回值会按订单送货行ID同序排列（升序）
     * @param mixed $orderNo 订单号
     * @param bool $register 是否使用寄存数据
     * @return array
     * @throws LogisException
     */
    public static function findRowsByOrderNo($orderNo, $register = true)
    {
        if (is_string($orderNo)) {
            $orderNo = [$orderNo];
        } elseif (!is_array($orderNo)) {
            throw new LogisException('传值错误');
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
     * 根据送货单号查询数据
     * 返回值会按订单送货表行ID同序排列（升序）
     * @param mixed $shipNo
     * @param bool $register
     * @return array
     * @throws LogisException
     */
    public static function findRowsByShipNo($shipNo, $register = true)
    {
        if (is_string($shipNo)) {
            $shipNo = [$shipNo];
        } elseif (!is_array($shipNo)) {
            throw new LogisException('传值错误');
        }
        if (empty(static::$register[static::channelId()]['link']['ship_no']) || !$register) {
            $newNo = $shipNo;
        } else {
            $newNo = array_diff($shipNo, array_keys(static::$register[static::channelId()]['link']['ship_no']));
        }
        if (!empty($newNo)) {
            $data = static::find()
                ->where(['IN', 'ship_no', $newNo])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register[static::channelId()]['link']['ship_no'][$row['ship_no']] = $row['id'];
                static::$register[static::channelId()]['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($shipNo as $no) {
            if (isset(static::$register[static::channelId()]['link']['ship_no'][$no])
                && isset(
                    static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['ship_no'][$no]
                    ]
                )
            ) {
                $result[static::$register[static::channelId()]['link']['ship_no'][$no]]
                    = static::$register[static::channelId()]['data'][
                        static::$register[static::channelId()]['link']['ship_no'][$no]
                    ];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 插入订单送货信息
     * @param $rows
     * @throws LogisException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        $lockedTime = null;
        if (!empty(Yii::$app->db->getTransaction())) { // 事务锁定（一个小时），commit后解锁
            $lockedTime = date('Y-m-d H:i:s', strtotime('+1 hours'));
            $shipNo = array_column($rows, 'ship_no');
            Yii::$app->db->on((Yii::$app->db)::EVENT_COMMIT_TRANSACTION, function () use ($shipNo) {
                static::updateAll(['locked_time' => '0000-00-00 00:00:00'], ['IN', 'ship_no', $shipNo]);
            });
        }
        array_walk($rows, function (&$value) use ($lockedTime) {
            if (strlen($value['ship_no']) > 20) {
                throw new LogisException('送货订单号异常');
            }
            if (empty($value['message'])) {
                $value['message'] = '';
            } else {
                $value['message'] = trim(htmlspecialchars(strip_tags($value['message']), ENT_QUOTES));
            }
            if (empty($lockedTime)) {
                unset($value['locked_time']);
            } else {
                $value['locked_time'] = $lockedTime;
            }
        });
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount != count($rows)) {
            throw new LogisException('订单送货信息创建异常，请重新尝试');
        }
    }

    const ABNORMAL = -1;     // 异常件
    const TO_BE_PAID = 0;    // 未支付
    const TO_BE_SHIPPED = 1; // 待发货
    const SHIPPED = 2;       // 已发货
    const SIGNED = 3;        // 已签收

    /**
     * 更新送货状态
     * @param $shipNo
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     */
    public static function updateStatByShipNo($shipNo, $stat, array $andWhere = [], array $params = [])
    {
        if (is_array($shipNo)) {
            $shipNo = array_filter($shipNo);
        } elseif (is_string($shipNo)) {
            $shipNo = [$shipNo];
        }
        $condition = ['IN', 'ship_no', $shipNo];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $params['ship_stat'] = $stat;
        if (is_int($stat) && $stat == static::SHIPPED && empty($params['ship_ntime'])) {
            $params['ship_ntime'] = date('Y-m-d H:i:s');
        }

        // 事务锁定（一个小时），commit后解锁
        if (!empty(Yii::$app->db->getTransaction())) {
            $params['locked_time'] = $lockedTime = date('Y-m-d H:i:s', strtotime('+1 hours'));
            Yii::$app->db->on((Yii::$app->db)::EVENT_COMMIT_TRANSACTION, function () use ($shipNo, $lockedTime) {
                static::updateAll(
                    ['locked_time' => '0000-00-00 00:00:00'],
                    ['AND', ['IN', 'ship_no', $shipNo], ['locked_time' => $lockedTime]]
                );
            });
        }

        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($shipNo)) {
            if (isset(static::$register[static::channelId()]['data'])) {
                foreach (static::$register[static::channelId()]['data'] as &$value) {
                    if (in_array($value['ship_no'], $shipNo)) {
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
