<?php

namespace app\modules\pay\modules\v1\models;

use yii\db\ActiveRecord;

class Payment extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%payments}}';
    }
}
