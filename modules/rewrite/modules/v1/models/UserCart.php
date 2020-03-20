<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class UserCart extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $rewriteChannel = SaleChannel::findRewriteChannel($channelAlias);
        return '{{coin.ecs_' . $rewriteChannel['tbl_prefix'] . 'user_cart_cookie}}';
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
     * 获取用户购物车信息
     * @param $rewriteUserId
     * @return mixed
     */
    public static function findCartItems($rewriteUserId)
    {
        if (!isset(static::$register[static::channelId()][$rewriteUserId])) {
            static::$register[static::channelId()][$rewriteUserId] = static::find()
                ->where(['user_id' => $rewriteUserId])
                ->asArray()
                ->one();
        }

        return static::$register[static::channelId()][$rewriteUserId];
    }

    /**
     * 根据用户ID更新购物车
     * @param $attributes
     * @param $rewriteUserId
     * @param $params
     */
    public static function updateCartByRewriteUserId($attributes, $rewriteUserId, $params = [])
    {
        $rowCount = parent::updateAll($attributes, ['user_id' => $rewriteUserId], $params);

        // 同步寄存器
        if ($rowCount > 0 && isset(static::$register[static::channelId()][$rewriteUserId])) {
            static::$register[static::channelId()][$rewriteUserId]
                = array_merge(static::$register[static::channelId()][$rewriteUserId], $attributes);
        }
    }
}
