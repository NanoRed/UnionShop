<?php

namespace app\modules\admin\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use app\modules\admin\modules\v1\components\ChannelHelper;
use app\modules\admin\modules\v1\exceptions\TableNotFoundException;

class Order extends ActiveRecord
{
    public function init()
    {
        parent::init();

        $valid = false;
        $tableName = static::tableName();
        if ($tableName != false) {
            if (static::getDb()->getTableSchema($tableName) !== null) {
                // 检验是否在权限内
                $channelId = Yii::$app->session->get((Yii::$app->controller)::className());
                $grantedChannels = ChannelHelper::getAssignedChannel(Yii::$app->user->id);
                if (!empty($grantedChannels) && in_array($channelId, array_column($grantedChannels, 'id'))) {
                    $valid = true;
                }
            }
        }
        if (!$valid) {
            throw new TableNotFoundException('报表不存在');
        }
    }

    public static function tableName()
    {
        $channelId = Yii::$app->session->get((Yii::$app->controller)::className());
        if (is_numeric($channelId) && $channelId > 0) {
            return "{{%{$channelId}_orders}}";
        } else {
            return false;
        }
    }

    const REFUNDED = -2;  // 已退款（全额或部分）
    const CANCELED = -1;  // 订单已取消（释放库存）
    const TO_BE_PAID = 0; // 等待支付
    const PAID = 1;       // 已支付
    const COMPLETE = 2;   // 已完成
}
