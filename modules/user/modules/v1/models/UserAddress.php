<?php

namespace app\modules\user\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class UserAddress extends ActiveRecord
{
    public static function channelId()
    {
        return Yii::$app->getModule('channel/v1')->processor->channelId;
    }
    
    public static function tableName()
    {
        return '{{%' . static::channelId() . '_user_addresses}}';
    }

    const SCENARIO_ADD = 'add';
    const SCENARIO_EDIT = 'edit';
    const SCENARIO_DEL = 'del';

    public function scenarios()
    {
        return [
            self::SCENARIO_ADD => [
                'user_id',
                'consignee',
                'phone_no',
                'zip_code',
                'dist_table',
                'dist_id',
                'addr_detail',
                'addr_stat',
                'create_time'
            ],
            self::SCENARIO_EDIT => [
                'consignee',
                'phone_no',
                'zip_code',
                'dist_table',
                'dist_id',
                'addr_detail',
            ],
            self::SCENARIO_DEL => [
                'addr_stat',
            ],
        ];
    }

    public function rules()
    {
        return [
            [
                'user_id',
                'filter',
                'filter' => function () {
                    return Yii::$app->user->identity->id;
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                'addr_stat',
                'filter',
                'filter' => function () {
                    return 1;
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                'addr_stat',
                'filter',
                'filter' => function () {
                    return 0;
                },
                'on' => self::SCENARIO_DEL
            ],
            [
                'create_time',
                'filter',
                'filter' => function () {
                    return date('Y-m-d H:i:s');
                },
                'on' => self::SCENARIO_ADD
            ],
            [
                ['consignee', 'phone_no', 'dist_table', 'addr_detail'],
                'filter',
                'filter' => function ($value) {
                    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES));
                },
                'on' => [self::SCENARIO_ADD, self::SCENARIO_EDIT]
            ],
            [
                ['consignee', 'phone_no', 'dist_table', 'dist_id', 'addr_detail'],
                'required',
                'isEmpty' => function ($value) {
                    return empty($value);
                },
                'on' => [self::SCENARIO_ADD, self::SCENARIO_EDIT]
            ],
            [
                'dist_id',
                'integer',
                'on' => [self::SCENARIO_ADD, self::SCENARIO_EDIT]
            ],
            [
                'zip_code',
                'filter',
                'filter' => function ($value) {
                    return empty($value) ? '' : $value;
                },
                'on' => [self::SCENARIO_ADD, self::SCENARIO_EDIT]
            ]
        ];
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
     * 获取用户送货地址信息
     * @param $addressId
     * @param bool $auth
     * @return mixed
     */
    public static function findUserAddressById($addressId, $auth = true)
    {
        if (!isset(static::$register[static::channelId()][$addressId])) {
            $query = static::find()
                ->where([
                    'id' => $addressId,
                    'addr_stat' => 1,
                ]);
            if ($auth) {
                $query->andWhere(['user_id' => Yii::$app->user->identity->id]);
            }
            $addressInfo = $query->asArray()->one();

            static::$register[static::channelId()][$addressId] = $addressInfo === null ? [] : $addressInfo;
        }

        return static::$register[static::channelId()][$addressId];
    }
}
