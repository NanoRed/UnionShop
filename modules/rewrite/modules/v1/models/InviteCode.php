<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class InviteCode extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_invite_code}}';
    }

    const UNTAPPED = 1; // 未使用
    const USED = 2;     // 已使用

    /**
     * 更新邀请码状态
     * @param $orderNo
     * @param $stat
     * @param array $andWhere
     * @param array $params
     * @return int
     * @throws \Exception
     */
    public static function updateStatByOrderSn($orderNo, $stat, array $andWhere = [], array $params = [])
    {
        $params['is_use'] = $stat;
        if ($stat == static::USED && empty($params['use_time'])) {
            $params['use_time'] = date('Y-m-d H:i:s');
        } elseif ($stat == static::UNTAPPED) {
            $params['use_time'] = null;
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
