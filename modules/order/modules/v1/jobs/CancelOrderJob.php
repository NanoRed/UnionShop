<?php

namespace app\modules\order\modules\v1\jobs;

use Yii;
use yii\base\Component;
use yii\queue\JobInterface;
use app\modules\order\modules\v1\models\Order;
use app\modules\rewrite\modules\v1\behaviors\CartItemBox;
use app\modules\order\modules\v1\exceptions\OrderException;

/**
 * 取消订单任务
 * Class CancelOrderJob
 * @package app\modules\order\modules\v1\jobs
 */
class CancelOrderJob extends Component implements JobInterface
{
    public function behaviors()
    {
        return [
            'cartItemBox' => ['class' => CartItemBox::className()]
        ];
    }

    public $channelAlias; // 渠道代号
    public $orderNo;      // 订单号

    public function execute($queue)
    {
        // 使用READ_UNCOMMITTED级别事务
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
        try {
            if (empty($this->channelAlias))
                throw new OrderException('渠道代号为空');
            if (empty($this->orderNo))
                throw new OrderException('订单号为空');

            // 渠道实例
            Yii::$app->getModule('channel/v1')->processor = $this->channelAlias; // 设置渠道进程
            $processor = Yii::$app->getModule('channel/v1')->processor;

            // 锁定需还原的订单
            $orders = Order::findRowsByOrderNo($this->orderNo); // 查询是否存在订单
            $lockedOrder = [];
            foreach ($orders as $value) {
                if ($value['order_stat'] == Order::TO_BE_PAID) {
                    // 先更新订单状态，锁定订单为正在执行（处理相同订单并发执行）
                    $num = Order::updateStatByOrderNo(
                        $value['order_no'], Order::CANCELED, ['order_stat' => Order::TO_BE_PAID]
                    );
                    if ($num == 1) {
                        $lockedOrder[] = $value['order_no'];
                    }
                }
            }

            // 恢复订单资源
            if (!empty($lockedOrder)) {
                // 恢复订单商品库存
                $this->restoreOrderItems($lockedOrder);

                // 营销工具接取消订单时恢复资源
                if ($processor->hasMethod('marketOrderCancel')) {
                    $processor->marketOrderCancel($lockedOrder);
                }
            }

            $task->commit();
        } catch (\Exception $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
        } catch (\Throwable $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
        }
    }
}
