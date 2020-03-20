<?php

namespace app\modules\pay\modules\v1\bases;

use Yii;
use yii\base\Component;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\pay\modules\v1\models\Refund;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\pay\modules\v1\models\TransferRelation;
use app\modules\pay\modules\v1\exceptions\PayException;
use app\modules\logis\modules\v1\models\OrderShipping;

/**
 * payment基础继承类
 * Class Payment
 * @package app\modules\pay\modules\v1\bases
 */
abstract class Payment extends Component
{
    public $params;       // 配置参数

    public $paymentId;    // 支付方式ID
    public $paymentAlias; // 支付方式代号
    public $accountId;    // 结算账户ID
    public $merchantId;   // 商户号

    public $xferNo;       // 支付订单号
    public $xferAmt;      // 支付总金额
    public $xferSn;       // 支付商交易流水号
    public $xferExt;      // 支付额外记录，非必须
    public $xferStat;     // 支付订单状态

    public $refundSn;     // 支付商退款订单号
    public $refundExt;    // 支付商退款额外记录，非必须

    /**
     * 执行支付实例服务
     * @param $method
     * @param array $params
     * @return mixed
     * @throws PayException
     */
    public function run($method, $params = [])
    {
        if (method_exists($this, $method)) {
            $beforeRunMethod = 'before' . ucfirst($method);
            if (method_exists($this, $beforeRunMethod)) {
                call_user_func_array([$this, $beforeRunMethod], $params);
            }

            call_user_func_array([$this, $method], $params);

            $afterRunMethod = 'after' . ucfirst($method);
            if (method_exists($this, $afterRunMethod)) {
                call_user_func_array([$this, $afterRunMethod], $params);
            }
        } else {
            throw new PayException('服务异常，该操作尚未实现');
        }
    }

    /**
     * 预执行验证
     * @throws PayException
     */
    public function beforePaid()
    {
        // 考虑到抛出异常处理，此服务须在事务中执行
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new PayException('非法执行');
        }
    }

    /**
     * 实现无需支付的0元订单
     * @param $orderNo
     * @return mixed
     */
    abstract function paid($orderNo);

    /**
     * 更新订单数据
     * @param $orderNo
     * @throws PayException
     * @throws \app\modules\logis\modules\v1\exceptions\LogisException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     * @throws \yii\db\Exception
     */
    public function afterPaid($orderNo)
    {
        // 请在paid()方法中完成必须参数填充
        if (is_null($this->xferNo)) {
            throw new PayException('数据异常，更新交易数据失败');
        }

        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 订单数据
        $orders = Order::findRowsByOrderNo($orderNo);
        OrderItem::findRowsByOrderNo($orderNo);
        $orderShipping = OrderShipping::findRowsByOrderNo($orderNo);
        $link = array_flip(array_column($orderShipping, 'order_no'));

        // 营销工具接收支付通知时的数据更新
        if ($processor->hasMethod('marketGetPaymentNotification')) {
            $processor->marketGetPaymentNotification(array_column($orders, 'order_no'));
        }

        // 更新渠道支付交易表
        $transfer = [
            'user_id' => Yii::$app->user->identity->id,
            'payment_id' => $this->paymentId,
            'account_id' => $this->accountId,
            'xfer_no' => $this->xferNo,
            'xfer_amt' => 0.00,
            'xfer_stat' => Transfer::PAID,
            'create_time' => date('Y-m-d H:i:s')
        ];
        Transfer::createRow($transfer);
        $xferId = Transfer::find()
            ->select(['id'])
            ->where(['xfer_no' => $transfer['xfer_no']])
            ->scalar();

        // 更新及关联
        $orderNoUpdate = $transferRelations = $shipNoUpdate = [];
        foreach ($orders as $value) {
            if ($value['order_stat'] == Order::TO_BE_PAID) {
                $orderNoUpdate[] = $value['order_no'];
                $transferRelations[] = [
                    'order_id' => $value['id'],
                    'xfer_id' => $xferId
                ];
                if ($orderShipping[$link[$value['order_no']]]['ship_stat'] == OrderShipping::TO_BE_PAID) {
                    $shipNoUpdate[$value['order_no']] = $orderShipping[$link[$value['order_no']]]['ship_no'];
                }
            }
        }
        if (!empty($orderNoUpdate)) {
            // 区分是否纯虚拟商品订单，纯虚拟商品订单可以直接完成订单
            $material = $virtual = [];
            foreach ($orderNoUpdate as $value) {
                $isMaterial = false;
                foreach(OrderItem::findRowsByOrderNo($value) as $val) {
                    if ($val['parent_id'] == 0 && !$isMaterial) {
                        $isMaterial = $val['item_is_virt'] == 0;
                    }
                }
                if ($isMaterial) {
                    $material[] = $value;
                } else {
                    $virtual[] = $value;
                }
            }
            unset($orderNoUpdate);
            if (!empty($material)) {
                $num = Order::updateStatByOrderNo($material, Order::PAID);
                if ($num != count($material)) {
                    throw new PayException('订单状态更新异常');
                }
            }
            if (!empty($virtual)) {
                $num = Order::updateStatByOrderNo($virtual, Order::COMPLETE);
                if ($num != count($virtual)) {
                    throw new PayException('订单状态更新异常');
                }
            }
            TransferRelation::createRows($transferRelations);
            if (!empty($shipNoUpdate)) {
                $material2 = $virtual2 = [];
                foreach ($shipNoUpdate as $key => $value) {
                    if (in_array($key, $virtual)) {
                        $virtual2[] = $value;
                    } else {
                        $material2[] = $value;
                    }
                }
                unset($shipNoUpdate);
                if (!empty($material2)) {
                    $num = OrderShipping::updateStatByShipNo($material2, OrderShipping::TO_BE_SHIPPED);
                    if ($num != count($material2)) {
                        throw new PayException('送货状态更新失败');
                    }
                }
                if (!empty($virtual2)) {
                    $num = OrderShipping::updateStatByShipNo($virtual2, OrderShipping::SIGNED);
                    if ($num != count($virtual2)) {
                        throw new PayException('送货状态更新失败');
                    }
                }
            }
        }
    }

    /**
     * 预执行验证
     * @throws PayException
     */
    public function beforeCall()
    {
        // 考虑到抛出异常处理，此服务须在事务中执行
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new PayException('非法执行');
        }
    }

    /**
     * 实现唤起支付
     * @param $orderNo
     * @return mixed
     */
    abstract function call($orderNo);

    /**
     * 更新支付交易数据
     * @param $orderNo
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     * @throws \yii\db\Exception
     */
    public function afterCall($orderNo)
    {
        // 请在call()方法中完成必须参数填充
        if (is_null($this->xferNo) || is_null($this->xferAmt)) {
            throw new PayException('数据异常，更新交易数据失败');
        }

        // 订单数据
        $orders = Order::findRowsByOrderNo($orderNo);

        // 更新渠道支付交易表
        $transfer = [
            'user_id' => Yii::$app->user->identity->id,
            'payment_id' => $this->paymentId,
            'account_id' => $this->accountId,
            'xfer_no' => $this->xferNo,
            'xfer_amt' => $this->xferAmt
        ];
        if (is_array($this->xferExt) && !empty($this->xferExt)) {
            $transfer['xfer_ext'] = json_encode(
                ['call' => $this->xferExt],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }
        $transfer['create_time'] = date('Y-m-d H:i:s');
        Transfer::createRow($transfer);
        $xferId = Transfer::find()
            ->select(['id'])
            ->where(['xfer_no' => $transfer['xfer_no']])
            ->scalar();

        // 更新渠道订单支付关联表
        $transferRelations = [];
        foreach ($orders as $value) {
            $transferRelations[] = [
                'order_id' => $value['id'],
                'xfer_id' => $xferId
            ];
        }
        TransferRelation::createRows($transferRelations);
    }

    /**
     * 预执行验证
     * @throws PayException
     */
    public function beforeCallback()
    {
        // 此服务须在事务中执行
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new PayException('非法执行');
        }
    }

    /**
     * 实现接收支付通知
     * @return mixed
     */
    abstract function callback();

    /**
     * 支付通知后更新数据
     * @throws PayException
     * @throws \app\modules\logis\modules\v1\exceptions\LogisException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function afterCallback()
    {
        // callback()方法中完成必须参数填充
        if (is_null($this->xferNo) || is_null($this->xferAmt) || is_null($this->xferSn)) {
            throw new PayException('数据异常，支付通知数据更新失败');
        }

        // 订单数据
        $transfer = Transfer::findRowsByXferNo($this->xferNo);
        $transfer = end($transfer);

        if (empty($transfer)) {
            throw new PayException('交易订单不存在');
        } elseif (bcsub($transfer['xfer_amt'], $this->xferAmt, 2) != 0) {
            throw new PayException('交易金额与订单金额不一致');
        } elseif ($transfer['xfer_stat'] == Transfer::TO_BE_PAID) {

            // 渠道实例
            $processor = Yii::$app->getModule('channel/v1')->processor;

            // 订单数据
            $relatedOrderNo = Transfer::findRelatedOrderNoByXferNo($this->xferNo);
            $orders = Order::findRowsByOrderNo($relatedOrderNo);
            OrderItem::findRowsByOrderNo($relatedOrderNo);
            $orderShipping = OrderShipping::findRowsByOrderNo($relatedOrderNo);
            $link = array_flip(array_column($orderShipping, 'order_no'));

            // 营销工具接收支付通知时的数据更新
            if ($processor->hasMethod('marketGetPaymentNotification')) {
                $processor->marketGetPaymentNotification($relatedOrderNo);
            }

            // 更新渠道支付交易表状态
            $transferParams = ['xfer_sn' => $this->xferSn];
            if (is_array($this->xferExt) && !empty($this->xferExt)) {
                if (empty($transfer['xfer_ext'])) {
                    $transferParams['xfer_ext'] = json_encode(
                        ['callback' => $this->xferExt],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } else {
                    $transferParams['xfer_ext'] = json_decode($transfer['xfer_ext'], true);
                    $transferParams['xfer_ext']['callback'] = $this->xferExt;
                    $transferParams['xfer_ext'] = json_encode(
                        $transferParams['xfer_ext'],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                }
            }
            $num = Transfer::updateStatByXferNo(
                $transfer['xfer_no'], Transfer::PAID, [], $transferParams
            );
            if ($num != 1) {
                throw new PayException('交易订单状态更新失败！');
            }

            // 更新状态
            $orderNoUpdate = $shipNoUpdate = [];
            foreach ($orders as $value) {
                if ($value['order_stat'] == Order::TO_BE_PAID) {
                    $orderNoUpdate[] = $value['order_no'];
                    if (
                        $orderShipping[$link[$value['order_no']]]['ship_stat'] == OrderShipping::TO_BE_PAID
                    ) {
                        $shipNoUpdate[$value['order_no']] = $orderShipping[$link[$value['order_no']]]['ship_no'];
                    }
                }
            }
            if (!empty($orderNoUpdate)) {
                // 区分是否纯虚拟商品订单，纯虚拟商品订单可以直接完成订单
                $material = $virtual = [];
                foreach ($orderNoUpdate as $value) {
                    $isMaterial = false;
                    foreach(OrderItem::findRowsByOrderNo($value) as $val) {
                        if ($val['parent_id'] == 0 && !$isMaterial) {
                            $isMaterial = $val['item_is_virt'] == 0;
                        }
                    }
                    if ($isMaterial) {
                        $material[] = $value;
                    } else {
                        $virtual[] = $value;
                    }
                }
                unset($orderNoUpdate);
                if (!empty($material)) {
                    $num = Order::updateStatByOrderNo($material, Order::PAID);
                    if ($num != count($material)) {
                        throw new PayException('订单状态更新异常');
                    }
                }
                if (!empty($virtual)) {
                    $num = Order::updateStatByOrderNo($virtual, Order::COMPLETE);
                    if ($num != count($virtual)) {
                        throw new PayException('订单状态更新异常');
                    }
                }
                if (!empty($shipNoUpdate)) {
                    $material2 = $virtual2 = [];
                    foreach ($shipNoUpdate as $key => $value) {
                        if (in_array($key, $virtual)) {
                            $virtual2[] = $value;
                        } else {
                            $material2[] = $value;
                        }
                    }
                    unset($shipNoUpdate);
                    if (!empty($material2)) {
                        $num = OrderShipping::updateStatByShipNo($material2, OrderShipping::TO_BE_SHIPPED);
                        if ($num != count($material2)) {
                            throw new PayException('送货状态更新失败');
                        }
                    }
                    if (!empty($virtual2)) {
                        $num = OrderShipping::updateStatByShipNo($virtual2, OrderShipping::SIGNED);
                        if ($num != count($virtual2)) {
                            throw new PayException('送货状态更新失败');
                        }
                    }
                }
            }
        } elseif (is_array($this->xferExt) && !empty($this->xferExt)) {
            $transferParams = [];
            if (empty($transfer['xfer_ext'])) {
                $transferParams['xfer_ext'] = json_encode(
                    ['callback' => $this->xferExt],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            } else {
                $transferParams['xfer_ext'] = json_decode($transfer['xfer_ext'], true);
                $transferParams['xfer_ext']['callback'] = $this->xferExt;
                $transferParams['xfer_ext'] = json_encode(
                    $transferParams['xfer_ext'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }

            $rowCount = Transfer::updateAll($transferParams, ['xfer_no' => $transfer['xfer_no']]);
            if ($rowCount != 1) {
                throw new PayException('记录交易额外信息失败');
            }
        }
    }

    /**
     * 预执行验证
     * @throws PayException
     */
    public function beforeQuery()
    {
        // 此服务须在事务中执行
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new PayException('非法执行');
        }
    }

    /**
     * 实现查询交易订单信息
     * @param $orderNo
     * @return mixed
     */
    abstract function query($orderNo);

    /**
     * 判断更新交易订单状态
     * 查询若为成功支付订单，但系统内尚未标记为成功订单，则走一遍支付回调流程
     * @param $orderNo
     * @throws PayException
     * @throws \app\modules\logis\modules\v1\exceptions\LogisException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function afterQuery($orderNo)
    {
        // query()方法中完成必须参数填充，没有请填false
        if (is_null($this->xferAmt) || is_null($this->xferSn) || is_null($this->xferStat)) {
            throw new PayException('数据异常，查询订单失败');
        } elseif ($this->xferStat == Transfer::PAID) { // 交易商接口查询订单为成功时

            // 订单数据
            $transfer = Transfer::findRowsByOrderNo($orderNo);
            $transfer = end($transfer); // 选择最新的交易订单

            if (empty($transfer)) {
                throw new PayException('交易订单不存在');
            } elseif ($transfer['xfer_stat'] == Transfer::TO_BE_PAID) { // 本地交易订单状态为未支付时

                // 检查订单金额
                if (bcsub($transfer['xfer_amt'], $this->xferAmt, 2) != 0) {
                    throw new PayException('交易金额与订单金额不一致');
                }

                // 渠道实例
                $processor = Yii::$app->getModule('channel/v1')->processor;

                // 订单数据
                $relatedOrderNo = Transfer::findRelatedOrderNoByXferNo($transfer['xfer_no']);
                $orders = Order::findRowsByOrderNo($relatedOrderNo);
                OrderItem::findRowsByOrderNo($relatedOrderNo);
                $orderShipping = OrderShipping::findRowsByOrderNo($relatedOrderNo);
                $link = array_flip(array_column($orderShipping, 'order_no'));

                // 营销工具数据更新
                if ($processor->hasMethod('marketGetPaymentNotification')) {
                    $processor->marketGetPaymentNotification(array_column($orders, 'order_no'));
                }

                // 更新渠道支付交易表状态
                $transferParams = ['xfer_sn' => $this->xferSn];
                if (is_array($this->xferExt) && !empty($this->xferExt)) {
                    if (empty($transfer['xfer_ext'])) {
                        $transferParams['xfer_ext'] = json_encode(
                            ['query' => $this->xferExt],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    } else {
                        $transferParams['xfer_ext'] = json_decode($transfer['xfer_ext'], true);
                        $transferParams['xfer_ext']['query'] = $this->xferExt;
                        $transferParams['xfer_ext'] = json_encode(
                            $transferParams['xfer_ext'],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    }
                }
                $num = Transfer::updateStatByXferNo(
                    $transfer['xfer_no'], Transfer::PAID, [], $transferParams
                );
                if ($num != 1) {
                    throw new PayException('交易订单状态更新失败！');
                }

                // 更新渠道订单表以及订单送货表状态
                $orderNoUpdate = $shipNoUpdate = [];
                foreach ($orders as $value) {
                    if ($value['order_stat'] == Order::TO_BE_PAID) {
                        $orderNoUpdate[] = $value['order_no'];
                        $shipStat = $orderShipping[$link[$value['order_no']]]['ship_stat'];
                        if ($shipStat == OrderShipping::TO_BE_PAID) {
                            $shipNoUpdate[$value['order_no']] = $orderShipping[$link[$value['order_no']]]['ship_no'];
                        }
                    }
                }
                if (!empty($orderNoUpdate)) {
                    // 区分是否纯虚拟商品订单，纯虚拟商品订单可以直接完成订单
                    $material = $virtual = [];
                    foreach ($orderNoUpdate as $value) {
                        $isMaterial = false;
                        foreach(OrderItem::findRowsByOrderNo($value) as $val) {
                            if ($val['parent_id'] == 0 && !$isMaterial) {
                                $isMaterial = $val['item_is_virt'] == 0;
                            }
                        }
                        if ($isMaterial) {
                            $material[] = $value;
                        } else {
                            $virtual[] = $value;
                        }
                    }
                    unset($orderNoUpdate);
                    if (!empty($material)) {
                        $num = Order::updateStatByOrderNo($material, Order::PAID);
                        if ($num != count($material)) {
                            throw new PayException('订单状态更新异常');
                        }
                    }
                    if (!empty($virtual)) {
                        $num = Order::updateStatByOrderNo($virtual, Order::COMPLETE);
                        if ($num != count($virtual)) {
                            throw new PayException('订单状态更新异常');
                        }
                    }
                    if (!empty($shipNoUpdate)) {
                        $material2 = $virtual2 = [];
                        foreach ($shipNoUpdate as $key => $value) {
                            if (in_array($key, $virtual)) {
                                $virtual2[] = $value;
                            } else {
                                $material2[] = $value;
                            }
                        }
                        unset($shipNoUpdate);
                        if (!empty($material2)) {
                            $num = OrderShipping::updateStatByShipNo($material2, OrderShipping::TO_BE_SHIPPED);
                            if ($num != count($material2)) {
                                throw new PayException('送货状态更新失败');
                            }
                        }
                        if (!empty($virtual2)) {
                            $num = OrderShipping::updateStatByShipNo($virtual2, OrderShipping::SIGNED);
                            if ($num != count($virtual2)) {
                                throw new PayException('送货状态更新失败');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 预执行验证
     * @param $refundNo
     * @throws PayException
     */
    public function beforeRefund($refundNo)
    {
        // 此服务须在事务中执行
        if (is_null(Yii::$app->db->getTransaction())) {
            throw new PayException('非法执行');
        }

        // 退款订单状态验证
        $refund = Refund::findRowsByRefundNo($refundNo);
        $refund = end($refund);
        if ($refund['refund_stat'] == Refund::CANCELED) {
            throw new PayException('此退款订单已取消');
        } elseif ($refund['refund_stat'] == Refund::REFUNDED) {
            throw new PayException('此订单已退款成功');
        }
    }

    /**
     * 实现退款
     * @param $refundNo
     * @return mixed
     */
    abstract function refund($refundNo);

    /**
     * 判断更新退款订单状态
     * @param $refundNo
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function afterRefund($refundNo)
    {
        // refund()方法中完成必须参数填充
        if (is_null($this->refundSn)) {
            throw new PayException('数据异常，退款数据更新失败');
        }

        // 订单数据
        $refund = Refund::findRowsByRefundNo($refundNo);
        $refund = end($refund);

        if ($refund['refund_stat'] == Refund::UNDER_REVIEW) {
            $refundParams = ['refund_sn' => $this->refundSn];
            if (is_array($this->refundExt) && !empty($this->refundExt)) {
                if (empty($refund['refund_ext'])) {
                    $refundParams['refund_ext'] = json_encode(
                        ['refund' => $this->refundExt],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } else {
                    $refundParams['refund_ext'] = json_decode($refund['refund_ext'], true);
                    $refundParams['refund_ext']['refund'] = $this->refundExt;
                    $refundParams['refund_ext'] = json_encode(
                        $refundParams['refund_ext'],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                }
            }
            // 更新退款状态
            $num = Refund::updateStatByRefundNo($refund['refund_no'], Refund::REFUNDED, [], $refundParams);
            if ($num != 1) {
                throw new PayException('退款订单状态更新失败！');
            }

            // 更新订单状态
            $order = Order::findRowsByRefundNo($refundNo);
            $num = Order::updateStatByOrderNo(
                array_column($order, 'order_no'), Order::REFUNDED, ['>=', 'order_stat', Order::PAID]
            );
            if ($num != count($order)) {
                throw new PayException('订单状态更新异常');
            }
        }
    }
}