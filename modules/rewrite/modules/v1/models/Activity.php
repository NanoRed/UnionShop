<?php

namespace app\modules\rewrite\modules\v1\models;

use yii\db\ActiveRecord;

class Activity extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_activity}}';
    }
}
