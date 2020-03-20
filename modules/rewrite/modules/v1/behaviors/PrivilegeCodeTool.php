<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\PrivilegeCode;
use app\modules\order\modules\v1\models\Order;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 特权码工具
 * Class PrivilegeCodeTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class PrivilegeCodeTool extends Behavior
{
    public $privilegeCodeIds = null;

    /**
     * 检验锁定特权码
     * @param $good
     * @param $cartItemsProperty
     * @throws RewriteException
     * @throws \yii\db\Exception
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        if ($good['is_privilege'] > 0) {
            if (!empty($cartItemsProperty[$good['goods_id']][4])) {
                $datetime = date('Y-m-d H:i:s');
                $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);
                $privilegeCodeTableName = PrivilegeCode::tableName();
                $paramPrefix = '@' . md5($privilegeCodeTableName . __METHOD__) . '_';
                $privilegeCodeId = $paramPrefix . 'privilege_code_id';
                $updateSQL = "UPDATE {$privilegeCodeTableName}
                    SET 
                        `id` = {$privilegeCodeId} := `id`, 
                        `user_id` = '{$rewriteUserId}', 
                        `is_use` = " . PrivilegeCode::USED . ", 
                        `use_time` = '{$datetime}', 
                        `is_notice` = 1 
                    WHERE 
                        `event_id` = {$good['is_privilege']} 
                        AND `code` = '{$cartItemsProperty[$good['goods_id']][4]}' 
                        AND `is_use` = " . PrivilegeCode::UNTAPPED . " 
                    LIMIT 1";
                $selectSQL = "SELECT {$privilegeCodeId}";
                $rowCount = PrivilegeCode::getDb()->createCommand($updateSQL)->execute();
                if ($rowCount == 1) {
                    $this->privilegeCodeIds[$good['goods_id']] = PrivilegeCode::getDb()
                        ->createCommand($selectSQL)
                        ->queryScalar();
                } else {
                    throw new RewriteException('特权码无效或重复使用');
                }
            } else {
                throw new RewriteException('特权码错误');
            }
        }
    }

    /**
     * 更新特权码使用订单号
     * @param $orders
     * @param $orderItems
     * @param $orderShipping
     * @throws RewriteException
     */
    public function marketOrderAdjust(&$orders, &$orderItems, &$orderShipping)
    {
        if (isset($this->privilegeCodeIds)) {
            $updatePrivilegeCode = [];
            foreach ($orderItems as $key => $value) {
                if ($value['parent_id'] > 0) {
                    continue;
                } elseif (isset($this->privilegeCodeIds[$value['item_id']])) {
                    $updatePrivilegeCode[$value['order_no']][] = $this->privilegeCodeIds[$value['item_id']];
                }
            }
            foreach ($updatePrivilegeCode as $key => $value) {
                $rowCount = PrivilegeCode::updateAll(['order_sn' => $key], ['IN', 'id', $value]);
                if ($rowCount <= 0) {
                    throw new RewriteException('特权码处理失败，请重新再试');
                }
            }
        }
    }

    /**
     * 取消订单时恢复特权码为未使用状态
     * @param $orderNo
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function marketOrderCancel($orderNo)
    {
        $orders = Order::findRowsByOrderNo($orderNo);
        $rewriteUserId = User::findRewriteUserIdByUnionUserId(reset($orders)['user_id']);
        PrivilegeCode::updateStatByOrderSn(
            $orderNo,
            PrivilegeCode::UNTAPPED,
            ['user_id' => $rewriteUserId, 'is_use' => PrivilegeCode::USED],
            ['user_id' => null, 'order_sn' => '', 'is_notice' => null]
        );
    }
}
