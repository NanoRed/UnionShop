<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\user\modules\v1\models\User AS UnionUser;

class User extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $rewriteChannel = SaleChannel::findRewriteChannel($channelAlias);
        return '{{coin.ecs_' . $rewriteChannel['tbl_prefix'] . 'users}}';
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
     * 根据渠道用户ID获取待重写系统用户ID
     * @param $channelUserId
     * @return mixed
     */
    public static function findRewriteUserIdByChannelUserId($channelUserId)
    {
        if (!isset(static::$register[static::channelId()][__FUNCTION__][$channelUserId])) {
            static::$register[static::channelId()][__FUNCTION__][$channelUserId] = static::find()
                ->select(['user_id'])
                ->where(['unique_user_id' => $channelUserId])
                ->scalar();
        }

        return static::$register[static::channelId()][__FUNCTION__][$channelUserId];
    }

    /**
     * 根据聚合系统用户ID获取待重写系统用户ID
     * @param $unionUserId
     * @return mixed
     */
    public static function findRewriteUserIdByUnionUserId($unionUserId)
    {
        if (!isset(static::$register[static::channelId()][__FUNCTION__][$unionUserId])) {
            $channelUserId = UnionUser::find()
                ->select(['cuid'])
                ->where(['id' => $unionUserId])
                ->scalar();
            static::$register[static::channelId()][__FUNCTION__][$unionUserId]
                = static::findRewriteUserIdByChannelUserId($channelUserId);
        }

        return static::$register[static::channelId()][__FUNCTION__][$unionUserId];
    }
}
