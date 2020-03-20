<?php

namespace app\modules\rewrite\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class FlashSaleBlacklist extends ActiveRecord
{
    public static function tableName()
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $rewriteChannel = SaleChannel::findRewriteChannel($channelAlias);
        return '{{coin.ecs_' . $rewriteChannel['tbl_prefix'] . 'black_list}}';
    }
}
