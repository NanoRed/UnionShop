<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class ExtraCost extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_extra_cost}}';
    }

    public function getExtraCostReward()
    {
        return $this
            ->hasMany(ExtraCostReward::className(), ['activity_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC]);
    }
}
