<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class CouponCode extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_coupon_code}}';
    }

    public function getCouponEvent()
    {
        return $this->hasOne(CouponEvent::className(), ['id' => 'event_id'])
            ->where(['is_open' => 1, 'record_status' => 1]);
    }

    const UNTAPPED = 1; // 未使用
    const USED = 2;     // 已使用
    const CANCEL = 3;   // 注销
    const FROZEN = 4;   // 冻结

    /**
     * 更新现金券状态
     * @param $orderNo
     * @param $stat
     * @param array $andWhere
     * @return int
     * @throws \Exception
     */
    public static function updateStatByOrderSn($orderNo, $stat, array $andWhere = [])
    {
        $params['is_use'] = $stat;
        if ($stat == static::USED && empty($params['use_time'])) {
            $params['use_time'] = date('Y-m-d H:i:s');
        } elseif ($stat == static::UNTAPPED) {
            $params['use_time'] = '0000-00-00 00:00:00';
        }
        if (empty($orderNo)) {
            $where = '0=1';
        } else {
            if (is_string($orderNo)) {
                $orderNo = [$orderNo];
            }
            $orderNo = array_filter($orderNo);
            $where = ['IN', 'order_sn', $orderNo];
            if (!empty($andWhere)) {
                $where = ['AND', $where, $andWhere];
            }
        }
        return static::updateAll($params, $where);
    }
}
