<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\InviteCode;
use app\modules\order\modules\v1\models\Order;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 邀请码工具
 * Class InvitationCodeTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class InvitationCodeTool extends Behavior
{
    public $inviteCodeIds = null;

    /**
     * 检验锁定邀请码
     * @param $good
     * @param $cartItemsProperty
     * @throws RewriteException
     * @throws \yii\db\Exception
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        if ($good['is_invite'] > 0) {
            if (!empty($cartItemsProperty[$good['goods_id']][3])) {
                $datetime = date('Y-m-d H:i:s');
                $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);
                $inviteCodeTableName = InviteCode::tableName();
                $paramPrefix = '@' . md5($inviteCodeTableName . __METHOD__) . '_';
                $inviteCodeId = $paramPrefix . 'invite_code_id';
                $updateSQL = "UPDATE {$inviteCodeTableName}
                    SET 
                        `id` = {$inviteCodeId} := `id`, 
                        `user_id` = '{$rewriteUserId}', 
                        `is_use` = " . InviteCode::USED . ", 
                        `use_time` = '{$datetime}' 
                    WHERE 
                        `event_id` = {$good['is_invite']} 
                        AND `code` = '{$cartItemsProperty[$good['goods_id']][3]}' 
                        AND IF(`type` = 2, true, `is_use` = " . InviteCode::UNTAPPED . ") 
                    LIMIT 1";
                $selectSQL = "SELECT {$inviteCodeId}";
                $rowCount = InviteCode::getDb()->createCommand($updateSQL)->execute();
                if ($rowCount == 1) {
                    $this->inviteCodeIds[$good['goods_id']] = InviteCode::getDb()
                        ->createCommand($selectSQL)
                        ->queryScalar();
                } else {
                    throw new RewriteException('邀请码无效或重复使用');
                }
            } else {
                throw new RewriteException('邀请码错误');
            }
        }
    }

    /**
     * 更新邀请码使用订单号
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @throws RewriteException
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        if (isset($this->inviteCodeIds)) {
            $updateInviteCode = [];
            foreach ($orderItems as $key => $value) {
                if ($value['parent_id'] > 0) {
                    continue;
                } elseif (isset($this->inviteCodeIds[$value['item_id']])) {
                    $updateInviteCode[$value['order_no']][] = $this->inviteCodeIds[$value['item_id']];
                }
            }
            foreach ($updateInviteCode as $key => $value) {
                $rowCount = InviteCode::updateAll(['order_sn' => $key], ['IN', 'id', $value]);
                if ($rowCount <= 0) {
                    throw new RewriteException('邀请码处理失败，请重新再试');
                }
            }
        }
    }

    /**
     * 取消订单时恢复邀请码为未使用状态
     * @param $orderNo
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function marketOrderCancel($orderNo)
    {
        $orders = Order::findRowsByOrderNo($orderNo);
        $rewriteUserId = User::findRewriteUserIdByUnionUserId(reset($orders)['user_id']);
        InviteCode::updateStatByOrderSn(
            $orderNo,
            InviteCode::UNTAPPED,
            ['user_id' => $rewriteUserId, 'is_use' => InviteCode::USED],
            ['user_id' => null, 'order_sn' => '']
        );
    }
}
