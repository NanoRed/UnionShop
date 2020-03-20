<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class FullReduce extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_full_reduce}}';
    }

    public function getFullReduceDetail()
    {
        return $this->hasMany(FullReduceDetail::className(), ['active_id' => 'id'])->where(['record_status' => 1]);
    }
}
