<?php

namespace app\modules\logis\modules\v1\erps\wangdiantong\libs\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\logis\modules\v1\exceptions\LogisException;

class WangdiantongPushedOrder extends ActiveRecord
{
    public static function tableName()
    {
        return 'erp_wdt_pushed_orders';
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
     * 根据渠道ID以及旺店通订单tid查询数据
     * 返回值会按旺店通订单表ID同序排列（升序）
     * @param $channelId
     * @param $tid
     * @param bool $register
     * @return array
     * @throws LogisException
     */
    public static function findRowsByChannelIdAndTid($channelId, $tid, $register = true)
    {
        if (is_string($tid)) {
            $tid = [$tid];
        }
        if (!is_array($tid) || !is_numeric($channelId)) {
            throw new LogisException('传值错误');
        }
        if (empty(static::$register['link']['channel_id_wdt_tid'][$channelId]) || !$register) {
            $newNo = $tid;
        } else {
            $newNo = array_diff($tid, array_keys(static::$register['link']['channel_id_wdt_tid'][$channelId]));
        }
        if (!empty($newNo)) {
            $data = static::find()
                ->where([
                    'AND',
                    ['=', 'channel_id', $channelId],
                    ['IN', 'wdt_tid', $newNo]
                ])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register['link']['channel_id_wdt_tid'][$row['channel_id']][$row['wdt_tid']] = $row['id'];
                static::$register['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($tid as $no) {
            if (isset(static::$register['link']['channel_id_wdt_tid'][$channelId][$no])
                && isset(static::$register['data'][static::$register['link']['channel_id_wdt_tid'][$channelId][$no]])) {
                $result[static::$register['link']['channel_id_wdt_tid'][$channelId][$no]]
                    = static::$register['data'][static::$register['link']['channel_id_wdt_tid'][$channelId][$no]];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 插入表数据
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
            throw new LogisException('旺店通推送订单数据更新失败');
        }
    }

    /**
     * 根据旺店通订单表id更新字段数据
     * @param mixed $id
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
