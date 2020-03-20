<?php

namespace app\modules\order\modules\v1\controllers;

use Yii;
use app\modules\user\modules\v1\bases\AuthController;
use app\modules\order\modules\v1\models\Order;
use app\modules\rewrite\modules\v1\behaviors\CartItemBox;
use app\modules\order\modules\v1\exceptions\OrderException;

/**
 * 取消订单
 * Class CancelController
 * @package app\modules\order\modules\v1\controllers
 */
class CancelController extends AuthController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['cartItemBox'] = ['class' => CartItemBox::className()];
        return $behaviors;
    }

    /**
     * 推入取消订单任务队列
     */
    public function actionIndex()
    {
        // 使用READ_UNCOMMITTED级别事务
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
        try {
            // 验证参数
            if (Yii::$app->request->post('orderNo') == null) {
                throw new OrderException('参数错误');
            }

            // 对应订单号
            $orderNo = Yii::$app->request->post('orderNo');

            // 渠道实例
            $processor = Yii::$app->getModule('channel/v1')->processor;

            // 先更新订单状态，锁定订单为正在执行（处理相同订单并发执行）
            $num = Order::updateStatByOrderNo(
                $orderNo,
                Order::CANCELED,
                ['order_stat' => Order::TO_BE_PAID, 'user_id' => Yii::$app->user->identity->id]
            );
            if ($num != 1) {
                $orders = Order::findRowsByOrderNo($orderNo);
                if (empty($orders)) {
                    throw new OrderException('订单不存在');
                } else {
                    foreach ($orders as $value) {
                        if ($value['order_stat'] == Order::CANCELED) {
                            throw new OrderException('订单已经被取消');
                        } elseif ($value['order_stat'] != Order::TO_BE_PAID) {
                            throw new OrderException('订单状态异常');
                        } elseif ($value['user_id'] != Yii::$app->user->identity->id) {
                            throw new OrderException('非本账户订单');
                        }
                    }
                }
            } else {
                // 恢复订单商品库存
                $this->restoreOrderItems($orderNo);

                // 营销工具接取消订单时恢复资源
                if ($processor->hasMethod('marketOrderCancel')) {
                    $processor->marketOrderCancel($orderNo);
                }
            }

            $task->commit();
        } catch (OrderException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Exception $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的服务异常，请联系客服');
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Throwable $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的服务异常，请联系客服');
            Yii::$app->response->data = Yii::$app->response->content = null;
        }
    }
}
