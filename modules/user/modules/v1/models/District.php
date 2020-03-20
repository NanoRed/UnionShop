<?php

namespace app\modules\user\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use app\modules\user\modules\v1\exceptions\UserException;

class District extends ActiveRecord
{
    public static $table = 'cn2017';

    public static function tableName()
    {
        return '{{%' . strtolower(static::$table) . '_districts}}';
    }

    /**
     * 更新方法对应缓存
     * @param $method
     */
    public static function updateCache($method)
    {
        $class = __CLASS__;
        $table = static::tableName();
        switch ($method) {
            case 'findDetailedDistrictById':
                $districts = static::find()->indexBy('id')->asArray()->all();
                $pids = array_flip(array_column($districts, 'pid'));
                foreach ($districts as $value) {
                    $key = Yii::$app->id . md5($class . '::' . $method . $table . $value['id']);
                    if (isset($pids[$value['id']]) || $value['stat'] == 0) {
                        if (Yii::$app->redis->exists($key) > 0) {
                            Yii::$app->redis->del($key);
                        }
                    } else {
                        $data = [[
                            'id' => $value['id'],
                            'name' => $value['name'],
                            'type' => $value['type']
                        ]];
                        $pid = $value['pid'];
                        while (isset($districts[$pid])) {
                            array_unshift(
                                $data,
                                [
                                    'id' => $districts[$pid]['id'],
                                    'name' => $districts[$pid]['name'],
                                    'type' => $districts[$pid]['type']
                                ]
                            );
                            $pid = $districts[$pid]['pid'];
                        }
                        $expire = mt_rand(3600*24*23, 3600*24*30); // 防缓存雪崩
                        Yii::$app->redis->setex($key, $expire, json_encode($data));
                    }
                }
                break;
            case 'findSubordinateListById':
                $districts = static::find()->indexBy('id')->asArray()->all();
                $ids = array_column($districts, 'id');
                array_walk($ids, function (&$value) {
                    $value = '_' . $value;
                });
                $districts = array_combine($ids, $districts);
                $association = [];
                foreach ($districts as $key => $value) {
                    $tmp = [
                        $key => [
                            'id' => $value['id'],
                        ]
                    ];
                    $pid = $value['pid'];
                    while ($pid > 0) {
                        $tmp = [
                            '_' . $pid => [
                                'id' => $pid,
                                'child' => $tmp
                            ]
                        ];
                        $pid = $districts['_' . $pid]['pid'];
                    }
                    $association = ArrayHelper::merge($association, $tmp);
                }
                $recursion = function ($association, $pid = 0) use (&$recursion, $districts, $table, $class, $method) {
                    $data = [];
                    foreach ($association as $value) {
                        $data[] = [
                            'id' => $districts['_' . $value['id']]['id'],
                            'name' => mb_convert_encoding($districts['_' . $value['id']]['name'], 'GBK', 'UTF-8'),
                            'type' => $districts['_' . $value['id']]['type'],
                        ];
                        if (isset($value['child'])) {
                            $recursion($value['child'], $value['id']);
                        } else {
                            $key = Yii::$app->id . md5($class . '::' . $method . $table . $value['id']);
                            if (Yii::$app->redis->exists($key) > 0) {
                                Yii::$app->redis->del($key);
                            }
                        }
                    }
                    array_multisort(array_column($data, 'name'), SORT_ASC, $data);
                    array_walk($data, function (&$value) {
                        $value['name'] = mb_convert_encoding($value['name'], 'UTF-8', 'GBK');
                    });
                    $key = Yii::$app->id . md5($class . '::' . $method . $table . $pid);
                    $expire = mt_rand(3600*24*23, 3600*24*30); // 防缓存雪崩
                    Yii::$app->redis->setex($key, $expire, json_encode($data));
                };
                $recursion($association);
                break;
            case 'checkIsTail':
                $districts = static::find()->asArray()->all();
                $pids = array_flip(array_column($districts, 'pid'));
                foreach ($districts as $value) {
                    $key = Yii::$app->id . md5($class . '::' . $method . $table . $value['id']);
                    if ($value['stat'] == 1) {
                        $expire = mt_rand(3600*24*23, 3600*24*30); // 防缓存雪崩
                        if (isset($pids[$value['id']])) {
                            Yii::$app->redis->setex($key, $expire, 0);
                        } else {
                            Yii::$app->redis->setex($key, $expire, 1);
                        }
                    } elseif ($value['stat'] == 0) {
                        if (Yii::$app->redis->exists($key) > 0) {
                            Yii::$app->redis->del($key);
                        }
                    }
                }
                break;
        }
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
     * 递归获取详细地区
     * @param $districtId
     * @return mixed
     */
    public static function findDetailedDistrictById($districtId)
    {
        $tableName = static::tableName();
        if (!isset(static::$register[__FUNCTION__][$tableName][$districtId])) {
            $key = Yii::$app->id . md5(__METHOD__ . $tableName . $districtId);
            $detailedDistrict = json_decode(Yii::$app->redis->get($key), true);
            if (empty($detailedDistrict)) {
                $paramPrefix = '@' . md5($tableName . __METHOD__) . '_';
                $id = $paramPrefix . 'district_id';
                $sql = "SELECT t1.id, t1.name, t1.type 
                    FROM (
                        SELECT 
                            {$id} AS id, 
                            (
                                SELECT {$id} := pid AS pid 
                                FROM {$tableName} 
                                WHERE id = {$id} 
                                LIMIT 1
                            ) AS pid 
                        FROM 
                            (SELECT {$id} := :id) vars, {$tableName} 
                        WHERE {$id} > 0
                    ) t2 
                    JOIN {$tableName} t1 
                    ON t1.id = t2.id 
                    ORDER BY t1.id";
                $detailedDistrict = static::findBySql($sql, [':id' => $districtId])
                    ->asArray()
                    ->all();

                // 量不高的情况，由于用户地区分散性，短期地区缓存使用率会不高，故尽量延迟缓存时间，暂时缓存一个月
                Yii::$app->redis->setex($key, 3600*24*30, json_encode($detailedDistrict));
            }
            static::$register[__FUNCTION__][$tableName][$districtId] = $detailedDistrict;
        }

        return static::$register[__FUNCTION__][$tableName][$districtId];
    }

    /**
     * 获取下级列表
     * @param int $districtId
     * @return mixed
     */
    public static function findSubordinateListById($districtId = 0)
    {
        $tableName = static::tableName();
        if (!isset(static::$register[__FUNCTION__][$tableName][$districtId])) {
            $key = Yii::$app->id . md5(__METHOD__ . $tableName . $districtId);
            $subordinateList = json_decode(Yii::$app->redis->get($key), true);
            if (empty($subordinateList)) {
                $subordinateList = static::find()
                    ->select(['id', 'name', 'type'])
                    ->where(['pid' => $districtId, 'stat' => 1])
                    ->orderBy('CONVERT(`name` USING GBK) ASC')
                    ->asArray()
                    ->all();

                // 量不高的情况，由于用户地区分散性，短期地区缓存使用率会不高，故尽量延迟缓存时间，暂时缓存一个月
                Yii::$app->redis->setex($key, 3600*24*30, json_encode($subordinateList));
            }
            static::$register[__FUNCTION__][$tableName][$districtId] = $subordinateList;
        }

        return static::$register[__FUNCTION__][$tableName][$districtId];
    }

    /**
     * 检查区域ID是否为表中最小划分
     * 如“省”、“市”、“镇”划分中的“镇”类型
     * @param $districtId
     * @return mixed
     * @throws UserException
     */
    public static function checkIsTail($districtId)
    {
        $tableName = static::tableName();
        if (!isset(static::$register[__FUNCTION__][$tableName][$districtId])) {
            $key = Yii::$app->id . md5(__METHOD__ . $tableName . $districtId);
            $existing = Yii::$app->redis->exists($key);
            if (empty($existing)) {
                $existing = static::find()->where(['id' => $districtId, 'stat' => 1])->exists();
                if ($existing) {
                    $isTail = (int)!static::find()->where(['pid' => $districtId])->exists();

                    // 量不高的情况，由于用户地区分散性，短期地区缓存使用率会不高，故尽量延迟缓存时间，暂时缓存一个月
                    Yii::$app->redis->setex($key, 3600*24*30, $isTail);
                } else {
                    throw new UserException('不存在的地区');
                }
            } else {
                $isTail = Yii::$app->redis->get($key);
            }
            static::$register[__FUNCTION__][$tableName][$districtId] = (bool)$isTail;
        }

        return static::$register[__FUNCTION__][$tableName][$districtId];
    }
}
