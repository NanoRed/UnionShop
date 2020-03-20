<?php

namespace app\modules\rewrite\modules\v1\models;

use yii\db\ActiveRecord;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

class SaleChannel extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_sale_channel}}';
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
     * 获取待重写系统渠道信息
     * @param $channelAlias
     * @return mixed
     * @throws RewriteException
     */
    public static function findRewriteChannel($channelAlias)
    {
        if (!isset(static::$register[$channelAlias])) {
            $rewriteChannel = static::find()
                ->select(['id', 'name', 'code', 'tbl_prefix'])
                ->where(['code' => $channelAlias, 'record_status' => 1])
                ->asArray()
                ->one();
            if (empty($rewriteChannel)) {
                throw new RewriteException('无效待重写渠道');
            }

            static::$register[$channelAlias] = $rewriteChannel;
        }

        return static::$register[$channelAlias];
    }
}
