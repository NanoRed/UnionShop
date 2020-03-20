<?php

namespace app\modules\admin\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class Channel extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%channels}}';
    }

    /**
     * 获取所有渠道
     * @return array|mixed|ActiveRecord[]
     */
    public static function findAllChannels()
    {
        $key = [__METHOD__];
        if (($list = Yii::$app->cache->get($key)) === false) {
            $list = static::find()->indexBy('id')->asArray()->all();
            Yii::$app->cache->set($key, $list, 3600);
        }

        return $list;
    }
}
