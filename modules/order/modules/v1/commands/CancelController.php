<?php

namespace app\modules\order\modules\v1\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\modules\channel\modules\v1\models\Channel;
use app\modules\order\modules\v1\models\Order;
use app\modules\rewrite\modules\v1\behaviors\CartItemBox;

/**
 * 取消过期订单
 * Class CancelController
 * @package app\modules\order\modules\v1\commands
 */
class CancelController extends Controller
{
    public function behaviors()
    {
        return [
            'cartItemBox' => ['class' => CartItemBox::className()]
        ];
    }

    /**
     * 全渠道每100订单事务循环
     * @return int
     * @throws \Exception
     */
    public function actionIndex()
    {
        $channels = Channel::findAllChannel();
        foreach ($channels as $channel) {
            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channel['alias'];
            $processor = Yii::$app->getModule('channel/v1')->processor;

            $loopLimit = 100; // 每个渠道一次限制循环100次防死循环，也可理解为顺利情况下查询最多10000个订单进行取消操作

            // 每100个订单循环
            while (
                // 查询过期订单，预留5分钟给队列消耗
                $orders = Order::find()
                    ->where([
                        'AND',
                        ['order_stat' => Order::TO_BE_PAID],
                        ['<=',  'create_time', date('Y-m-d H:i:s', strtotime('-1 day -5 min'))]
                    ])
                    ->orderBy('id')
                    ->limit(100)
                    ->all()

            ) {
                $loopLimit--;
                if ($loopLimit < 0) break;

                // 使用READ_UNCOMMITTED级别事务
                $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
                try {
                    $lockedOrder = []; // 锁定的订单号
                    foreach ($orders as $order) {
                        if ($order['order_stat'] == Order::TO_BE_PAID) {
                            // 先更新订单状态，锁定订单为正在执行（处理相同订单并发执行）
                            $num = Order::updateStatByOrderNo(
                                $order['order_no'],
                                Order::CANCELED,
                                ['order_stat' => Order::TO_BE_PAID]
                            );
                            if ($num == 1) {
                                $lockedOrder[] = $order['order_no'];
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
                    return ExitCode::UNSPECIFIED_ERROR;
                } catch (\Throwable $e) {
                    $task->rollBack();
                    Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
                    return ExitCode::UNSPECIFIED_ERROR;
                }
            }
        }

        return ExitCode::OK;
    }
}
