<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class CouponEvent extends ActiveRecord
{
    public static function tableName()
    {
        return '{{coin.ecs_coupon_event}}';
    }

    public function getCoupon()
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $rewriteChannel = SaleChannel::findRewriteChannel($channelAlias);
        return $this->hasOne(Coupon::className(), ['id' => 'coupon_id'])
            ->where(['channel_id' => $rewriteChannel['id'], 'status' => 1]);
    }
}
