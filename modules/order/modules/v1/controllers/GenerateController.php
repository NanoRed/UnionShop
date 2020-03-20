<?php

namespace app\modules\order\modules\v1\controllers;

use Yii;
use yii\filters\RateLimiter;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\user\modules\v1\models\UserAddress;
use app\modules\logis\modules\v1\models\OrderShipping;
use app\modules\user\modules\v1\bases\AuthController;
use app\modules\order\modules\v1\jobs\CancelOrderJob;
use app\modules\order\modules\v1\exceptions\OrderException;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;
use app\modules\user\modules\v1\exceptions\UserException;
use app\modules\pay\modules\v1\exceptions\PayException;

/**
 * 订单生成
 * Class GenerateController
 * @package app\modules\order\modules\v1\controllers
 */
class GenerateController extends AuthController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['rateLimiter'] = [
            'class' => RateLimiter::className(),
            'enableRateLimitHeaders' => false,
        ];
        return $behaviors;
    }

    /**
     * 下单接口
     */
    public function actionIndex()
    {
        // 开启事务，指定 READ_UNCOMMITTED 隔离级别（最低级别），因为涉及渠道公共使用的表
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
        try {
            $processor = Yii::$app->getModule('channel/v1')->processor; // 渠道实例

            /*****************************************************
             * 检验参数                                          *
             * @param int paymentAccount 支付账户ID，必须        *
             * @param int shippingAddress 发货地址ID，或必须     *
             * @param int couponTicket 现金券ID，非必须          *
             * @param string leavingMessage 会员下单留言，非必须 *
             *****************************************************/
            if (Yii::$app->request->post('shippingAddress') == null) {
                throw new OrderException('请填写选择发货地址');
            } elseif (Yii::$app->request->post('paymentAccount') == null) {
                throw new OrderException('请选择支付方式');
            }

            /***********************************
             *  订单、订单详情、订单送货等数据 *
             ***********************************/
            $orders = []; // 订单详情
            $orderItems = $processor->getBehavior('order')->orderCartItemExtract(); // 提取购物车商品 订单商品详情
            $orderShipping = []; // 订单送货详情

            $separateOrder = []; // 分单号
            $addressInfo = UserAddress::findUserAddressById(Yii::$app->request->post('shippingAddress')); // 地址
            if (empty($addressInfo)) {
                throw new UserException('无效地址，请联系客服');
            }

            $datetime = date('Y-m-d H:i:s');

            // 生成完整订单数据
            foreach ($orderItems as $key => $value) {
                $pid = $value['parent_id'];
                while ($pid > 0) { // 子商品项跟父商品放在同一个订单
                    $value['item_is_virt'] = $orderItems[($pid - 1)]['item_is_virt'];
                    $value['item_warehouse_id'] = $orderItems[($pid - 1)]['item_warehouse_id'];
                    $value['item_supplier_id'] = $orderItems[($pid - 1)]['item_supplier_id'];
                    $pid = $orderItems[($pid - 1)]['parent_id'];
                }
                if ($value['item_is_virt'] == 0) { // 0实物商品，按仓库拆单
                    $signKey = $value['item_warehouse_id'];
                } else { // 1虚拟商品，按仓库和供应商拆单
                    $signKey = $value['item_warehouse_id'] . '_' . $value['item_supplier_id'];
                }
                if (empty($separateOrder[$signKey])) {
                    $orderNo = $separateOrder[$signKey] = $processor->getBehavior('order')->orderNumberGenerate();
                } else {
                    $orderNo = $separateOrder[$signKey];
                }
                $orderItems[$key] = array_merge(
                    [
                        'user_id' => Yii::$app->user->identity->id,
                        'order_no' => $orderNo,
                    ],
                    $value
                );
                $orderItems[$key]['create_time'] = $datetime;

                // 该项商品费用
                $itemCost = bcmul($value['item_xfer_price'], $value['item_number'], 2);

                if (empty($orders[$orderNo])) {
                    $orders[$orderNo] = [
                        'user_id' => Yii::$app->user->identity->id,
                        'order_no' => $orderNo,
                        'order_amt' => $itemCost,
                        'order_stat' => 0,
                        'create_time' => $datetime
                    ];
                    $orderShipping[] = [
                        'user_id' => Yii::$app->user->identity->id,
                        'consignee' => $addressInfo['consignee'],
                        'phone_no' => $addressInfo['phone_no'],
                        'zip_code' => $addressInfo['zip_code'],
                        'dist_table' => $addressInfo['dist_table'],
                        'dist_id' => $addressInfo['dist_id'],
                        'addr_detail' => $addressInfo['addr_detail'],
                        'message' => Yii::$app->request->post('leavingMessage', ''),
                        'order_no' => $orderNo,
                        'ship_no' => $processor->getBehavior('logis')->logisShippingOrderNumberGenerate(),
                        'ship_stat' => OrderShipping::TO_BE_PAID,
                        'create_time' => $datetime,
                    ];
                } else {
                    $orders[$orderNo]['order_amt'] = bcadd($orders[$orderNo]['order_amt'], $itemCost, 2);
                }
            }

            /*************************
             *  营销工具订单价格调整 *
             *************************/
            if ($processor->hasMethod('marketOrderAdjust')) {
                list(
                    $orders, $orderItems, $orderShipping
                ) = $processor->marketOrderAdjust(
                    $orders, $orderItems, $orderShipping
                );
            }

            /*************
             *  数据保存 *
             *************/
            Order::createRows($orders); // 更新渠道订单表
            OrderItem::createRows($orderItems); // 更新渠道订单商品表
            OrderShipping::createRows($orderShipping); // 更新渠道订单送货表

            // 执行支付
            $payment = Yii::$app
                ->getModule('pay/v1')
                ->getPayment(Yii::$app->request->post('paymentAccount'));

            $needPay = array_sum(array_column($orders, 'order_amt')) > 0; // 是否需要支付

            if ($needPay) {
                $payment->run('call', [$separateOrder]); // 唤起支付
            } else {
                $payment->run('paid', [$separateOrder]); // 0元订单
            }

            $task->commit();

            // 推入取消订单队列，延迟24小时后自动执行（24小时后自动取消订单释放资源）
            if ($needPay) {
                Yii::$app->queueOrder->delay(3600 * 24)->push(
                    new CancelOrderJob(['channelAlias' => $processor->channelAlias, 'orderNo' => $separateOrder])
                );
            }
        } catch (OrderException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (UserException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (RewriteException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (PayException $e) { // 支付报错信息比较敏感，不返回给客户端
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的支付服务异常，请联系客服');
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

    public function actionRePayment()
    {
        // 开启事务，指定 READ_UNCOMMITTED 隔离级别（最低级别），因为涉及渠道公共使用的表
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
        try {
            /************************************************
             * 检验参数                                     *
             * @param string orderNo 订单号，必须           *
             * @param int paymentAccount 支付的账户ID，必须 *
             ************************************************/
            if (Yii::$app->request->post('orderNo') == null) {
                throw new OrderException('无法找到订单');
            } elseif (Yii::$app->request->post('paymentAccount') == null) {
                throw new OrderException('请选择支付方式');
            }

            /*************
             *  校验订单 *
             *************/
            $order = Order::findRowsByOrderNo([Yii::$app->request->post('orderNo')]);
            $order = end($order);
            if (empty($order)) {
                throw new OrderException('此订单不存在');
            } elseif ($order['order_stat'] == Order::TO_BE_PAID) {
                # Normal Status. Pass
            } elseif ($order['order_stat'] == Order::REFUNDED) {
                throw new OrderException('此订单已进行了退款，无法再次支付');
            } elseif ($order['order_stat'] == Order::CANCELED) {
                throw new OrderException('此订单因长时间未支付已被取消');
            } elseif ($order['order_stat'] >= Order::PAID) {
                throw new OrderException('此订单已支付成功，无法再次支付');
            } else {
                throw new OrderException('此订单非待支付状态，无法支付');
            }

            /*************
             *  执行支付 *
             *************/
            $payment = Yii::$app
                ->getModule('pay/v1')
                ->getPayment(Yii::$app->request->post('paymentAccount'));

            $payment->run('query', [$order['order_no']]); // 接口查询支付状态

            if ($payment->xferStat != Transfer::PAID) {
                if ($order['order_amt'] > 0) {
                    $payment->run('call', [$order['order_no']]); // 唤起支付
                } else {
                    $payment->run('paid', [$order['order_no']]); // 0元订单
                }
            }

            $task->commit();

        } catch (OrderException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (UserException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (RewriteException $e) {
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (PayException $e) { // 支付报错信息比较敏感，不返回给客户端
            $task->rollBack();
            Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的支付服务异常，请联系客服');
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
