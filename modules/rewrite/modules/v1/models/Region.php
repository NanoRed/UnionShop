<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class Region extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_region}}';
    }
}
