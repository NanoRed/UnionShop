<?php

namespace app\modules\item\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\logis\modules\v1\models\OrderShipping;

class ProductReview extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_product_reviews}}';
    }

    public function getOrderShipping()
    {
        return $this->hasOne(OrderShipping::className(), ['order_no' => 'order_no']);
    }

    const SCENARIO_ADD = 'add';

    public function scenarios()
    {
        return [
            self::SCENARIO_ADD => [
                'user_id',
                'order_no',
                'item_id',
                'comment',
                'grade',
                'valid',
                'create_time',
            ],
        ];
    }

    public function rules()
    {
        return [
            [
                'user_id',
                'default',
                'value' => Yii::$app->user->identity->id,
                'on' => self::SCENARIO_ADD
            ],
            [
                'user_id',
                'filter',
                'filter' => function () {
                    return Yii::$app->user->identity->id;
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                'comment',
                'filter',
                'filter' => function ($value) {
                    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES));
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                'valid',
                'default',
                'value' => 1,
                'on' => self::SCENARIO_ADD
            ],
            [
                'valid',
                'filter',
                'filter' => function () {
                    return 1;
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                'create_time',
                'default',
                'value' => date('Y-m-d H:i:s'),
                'on' => self::SCENARIO_ADD
            ],
            [
                'create_time',
                'datetime',
                'format' => 'php:Y-m-d H:i:s',
                'on' => self::SCENARIO_ADD
            ],
            [
                ['user_id', 'order_no', 'item_id', 'comment', 'create_time'],
                'required',
                'isEmpty' => function ($value) {
                    return empty($value);
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                ['user_id', 'item_id', 'grade', 'valid'],
                'integer',
                'on' => self::SCENARIO_ADD
            ],
        ];
    }

    const VALID = 1;   // 有效评价
    const INVALID = 0; // 无效评价
}
