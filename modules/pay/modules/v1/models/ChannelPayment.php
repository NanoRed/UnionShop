<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class ChannelPayment extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%channel_payments}}';
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
     * 查询渠道当前设置的支付账户
     * @param $channelId
     * @return mixed
     */
    public static function findChannelPaymentAccount($channelId)
    {
        if (!isset(static::$register[$channelId])) {
            $key = Yii::$app->id . md5(__METHOD__ . $channelId);
            $channelPaymentAccount = json_decode(Yii::$app->redis->get($key), true);
            if (empty($channelPaymentAccount)) {
                $channelPaymentAccount = static::find()
                    ->select(['account_id'])
                    ->where(['channel_id' => $channelId, 'stat' => 1])
                    ->asArray()
                    ->column();
                Yii::$app->redis->setex($key, 3600, json_encode($channelPaymentAccount)); // 缓存一小时
            }
            static::$register[$channelId] = $channelPaymentAccount;
        }

        return static::$register[$channelId];
    }
}
