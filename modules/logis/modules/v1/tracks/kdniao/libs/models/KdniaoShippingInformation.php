<?php

namespace app\modules\logis\modules\v1\tracks\kdniao\libs\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\logis\modules\v1\exceptions\LogisException;

class KdniaoShippingInformation extends ActiveRecord
{
    public static function tableName()
    {
        return 'track_kdn_shipping_information';
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
     * 根据物流商单号查找数据
     * 返回值会按行ID同序排列（升序）
     * @param $shipmentNumber
     * @param bool $register
     * @return array
     * @throws LogisException
     */
    public static function findRowsByShipmentNumber($shipmentNumber, $register = true)
    {
        if (is_string($shipmentNumber)) {
            $shipmentNumber = [$shipmentNumber];
        } elseif (!is_array($shipmentNumber)) {
            throw new LogisException('传值错误');
        }
        if (empty(static::$register['link']['shipment_number']) || !$register) {
            $newNo = $shipmentNumber;
        } else {
            $newNo = array_diff($shipmentNumber, array_keys(static::$register['link']['shipment_number']));
        }
        if (!empty($newNo)) {
            $data = static::find()
                ->where(['IN', 'shipment_number', $newNo])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register['link']['shipment_number'][$row['shipment_number']][$row['courier_code']]
                    = $row['id'];
                static::$register['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($shipmentNumber as $no) {
            if (isset(static::$register['link']['shipment_number'][$no])) {
                foreach (static::$register['link']['shipment_number'][$no] as $courierCode => $id) {
                    if (isset(static::$register['data'][$id])) {
                        $result[$id] = static::$register['data'][$id];
                    }
                }
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 创建物流追踪表项
     * @param $rows
     * @throws LogisException
     * @throws \yii\db\Exception
     */
    public static function createRows($rows)
    {
        $rowCount = Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), array_keys(reset($rows)), $rows)
            ->execute();
        if ($rowCount != count($rows)) {
            throw new LogisException('快递鸟物流追踪信息创建异常，请重新尝试');
        }
    }

    // 识别物流商的方式
    const WAY_MAPPING = 1;           // 映射表
    const WAY_REQUEST = 2;           // 接口请求
    const WAY_FUZZY = 3;             // 模糊

    // 订阅状态
    const SUB_STAT_ERROR = -1;       // 订阅异常
    const SUB_STAT_WAITING = 0;      // 待订阅
    const SUB_STAT_SUCCESS = 1;      // 订阅成功

    // 是否已接收到推送
    const CALL_WAITING = 0;          // 等待接收推送
    const CALL_SUCCESS = 1;          // 成功接收推送

    // 推送物流状态
    const TRACE_STAT_NONE = 0;       // 无轨迹
    const TRACE_STAT_COLLECTED = 1;  // 已揽收
    const TRACE_STAT_ON_THE_WAY = 2; // 在途中
    const TRACE_STAT_SIGNED = 3;     // 已签收
    const TRACE_STAT_ABNORMAL = 4;   // 问题件

    /**
     * 根据ID更新订阅状态
     * @param $id
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     */
    public static function updateSubscriptionStatById($id, $stat, array $andWhere = [], array $params = [])
    {
        if (is_array($id)) {
            $id = array_filter($id);
        } elseif (is_string($id)) {
            $id = [$id];
        }
        $condition = ['IN', 'id', $id];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $params['subscription_stat'] = $stat;
        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($id)) {
            foreach ($id as $i) {
                if (isset(static::$register['data'][$i])) {
                    static::$register['data'][$i] = array_merge(static::$register['data'][$i], $params);
                }
            }
        } elseif ($rowCount > 0) {
            static::clearRegister();
        }

        return $rowCount;
    }

    /**
     * 根据ID更新字段数据
     * @param $id
     * @param array $params
     * @param array $andWhere
     * @return int
     */
    public static function updateRowsById($id, array $params, array $andWhere = [])
    {
        if (is_array($id)) {
            $id = array_filter($id);
        } elseif (is_string($id)) {
            $id = [$id];
        }
        $condition = ['IN', 'id', $id];
        if (!empty($andWhere)) {
            $condition = ['AND', $condition, $andWhere];
        }
        $rowCount = static::updateAll($params, $condition);

        // 同步寄存器
        if ($rowCount == count($id)) {
            foreach ($id as $key) {
                if (!empty(static::$register['data'][$key])) {
                    static::$register['data'][$key]
                        = array_merge(static::$register['data'][$key], $params);
                }
            }
        } elseif ($rowCount > 0) {
            static::clearRegister();
        }

        return $rowCount;
    }
}
