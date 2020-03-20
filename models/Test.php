<?php

namespace app\models;

use yii\db\ActiveRecord;

class Test extends ActiveRecord
{
    public function rules()
    {
        return [
            // ['id', 'required'],
            ['!id', 'integer'],
            ['content', 'string', 'length'=>[0, 5]]
        ];
    }
}
