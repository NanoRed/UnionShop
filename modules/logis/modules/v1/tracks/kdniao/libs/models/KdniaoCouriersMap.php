<?php

namespace app\modules\logis\modules\v1\tracks\kdniao\libs\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\logis\modules\v1\exceptions\LogisException;

class KdniaoCouriersMap extends ActiveRecord
{
    public static function tableName()
    {
        return 'track_kdn_couriers_map';
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
     * 根据关联物流商名称查找物流商编码
     * 返回值会按行ID同序排列（升序）
     * @param $relCourierName
     * @param bool $register
     * @return array
     * @throws LogisException
     */
    public static function findRowsByRelCourierName($relCourierName, $register = true)
    {
        if (is_string($relCourierName)) {
            $relCourierName = [$relCourierName];
        } elseif (!is_array($relCourierName)) {
            throw new LogisException('传值错误');
        }
        if (empty(static::$register['link']['rel_courier_name']) || !$register) {
            $newName = $relCourierName;
        } else {
            $newName = array_diff($relCourierName, array_keys(static::$register['link']['rel_courier_name']));
        }
        if (!empty($newName)) {
            $data = static::find()
                ->where(['IN', 'rel_courier_name', $newName])
                ->asArray()
                ->all();
            foreach ($data as $row) {
                static::$register['link']['rel_courier_name'][$row['rel_courier_name']] = $row['id'];
                static::$register['data'][$row['id']] = $row;
            }
        }
        $result = [];
        foreach ($relCourierName as $name) {
            if (isset(static::$register['link']['rel_courier_name'][$name])
                && isset(static::$register['data'][static::$register['link']['rel_courier_name'][$name]])) {
                $result[static::$register['link']['rel_courier_name'][$name]]
                    = static::$register['data'][static::$register['link']['rel_courier_name'][$name]];
            }
        }
        ksort($result);
        $result = array_values($result);

        return $result;
    }

    /**
     * 创建映射
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
            throw new LogisException('快递鸟物流公司编码映射创建异常，请重新尝试');
        }
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
                    static::$register['data'][$key] = array_merge(static::$register['data'][$key], $params);
                }
            }
        } elseif ($rowCount > 0) {
            static::clearRegister();
        }

        return $rowCount;
    }
}
