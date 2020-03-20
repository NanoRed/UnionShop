<?php

namespace app\modules\channel\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class Channel extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%channels}}';
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
     * 根据渠道代号查询渠道ID
     * @param $channelAlias
     * @return mixed
     */
    public static function findChannelIdByChannelAlias($channelAlias)
    {
        if (!isset(static::$register[$channelAlias])) {
            $key = Yii::$app->id . md5(__METHOD__ . $channelAlias);
            $channelId = Yii::$app->redis->get($key);
            if (empty($channelId)) {
                $channelId = Channel::find()->select(['id'])->where(['alias' => $channelAlias])->scalar();
                Yii::$app->redis->setex($key, 3600*24, $channelId); // 缓存一天
            }
            static::$register[$channelAlias] = $channelId;
        }

        return static::$register[$channelAlias];
    }

    /**
     * 查找所有渠道信息
     * 常用方法，必要优化时可进行缓存处理，集中于此处的意义也在于可以集中处理
     * @return array
     */
    public static function findAllChannel()
    {
        return static::find()->asArray()->all();
    }
}
