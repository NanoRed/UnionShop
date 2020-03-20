<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class GoldActivityMember extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_gold_user_data}}';
    }
}
