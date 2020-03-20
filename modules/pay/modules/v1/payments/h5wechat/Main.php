<?php

namespace app\modules\pay\modules\v1\payments\h5wechat;

use Yii;
use yii\base\Event;
use yii\helpers\Url;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\pay\modules\v1\bases\Payment;
use app\modules\pay\modules\v1\models\Refund;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\pay\modules\v1\exceptions\PayException;
use app\modules\pay\modules\v1\payments\h5wechat\libs\behaviors\Crypt;

/**
 * 微信H5支付
 * Class Main
 * @package app\modules\pay\modules\v1\payments\h5wechat
 */
class Main extends Payment
{
    public function init()
    {
        parent::init();

        $this->params = require __DIR__ . '/libs/config/params.php';
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['crypt'] = ['class' => Crypt::className()];
        return $behaviors;
    }

    /**
     * 无需支付0元订单
     * @param $orderNo
     * @return mixed|void
     */
    public function paid($orderNo)
    {
        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 填充必要支付订单号
        $this->xferNo = $processor->getBehavior('pay')->payTransferOrderNumberGenerate();

        // 拼入订单号参数
        $resolveHash = explode('#', $processor->wapPaidGuide, 2);
        $resolveParam = explode('?', $resolveHash[0], 2);
        if (!empty($resolveParam[1])) {
            parse_str($resolveParam[1], $data);
        }
        $data['order_no'] = $orderNo;
        $resolveHash[0] = $resolveParam[0] . '?' . http_build_query($data);
        $guidePage = implode('#', $resolveHash);

        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set(
            'X-Unionsystem-Script',
            str_replace(
                ["\n", "\r"], '',
                Yii::$app->controller->render(
                    '@app/modules/pay/modules/v1/payments/h5wechat/libs/views/location',
                    ['href' => $guidePage]
                )
            )
        );
    }

    /**
     * 唤起支付
     * @param $orderNo
     * @return mixed|void
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function call($orderNo)
    {
        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 订单数据
        $orders = Order::findRowsByOrderNo($orderNo);
        $orderItems = OrderItem::findRowsByOrderNo($orderNo);

        // 生成支付订单号
        $this->xferNo = $processor->getBehavior('pay')->payTransferOrderNumberGenerate();

        // 支付金额计算
        $this->xferAmt = 0;
        foreach ($orders as $value) {
            $this->xferAmt = bcadd($this->xferAmt, $value['order_amt'], 2);
        }

        // 生成支付描述
        $xferDesc = reset($orderItems)['item_name'] . (count($orderItems) > 1 ? '等' : '');
        $xferDesc = $processor->appName . '-' . $xferDesc;
        if (strlen($xferDesc) > 100) {
            $xferDesc = mb_substr(substr($xferDesc, 0, 90), 0, -1) . '...';
        }

        // 请求参数
        $reqParams = [
            'appid' => isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
                $this->params['merchant'][$this->merchantId][$processor->channelAlias]['appId'] :
                $this->params['merchant'][$this->merchantId]['default']['appId'],
            'mch_id' => $this->merchantId,
            'nonce_str' => md5(uniqid(microtime(), true)),
            'body' => $xferDesc,
            'out_trade_no' => $this->xferNo,
            'total_fee' => bcmul($this->xferAmt, 100),
            'spbill_create_ip' => Yii::$app->request->userIP,
            'notify_url' => Url::toRoute(
                '/pay/v1/callback/payment/' .
                Yii::$app->security->maskToken(
                    implode(',', [$processor->channelAlias, $this->accountId])
                ),
                true
            ),
            'trade_type' => 'MWEB', // H5
            'scene_info' => json_encode(['h5_info' => [
                'type' => 'Wap',
                'wap_url' => $processor->wapHomePage,
                'wap_name' => $processor->appName
            ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        // 接口请求
        $result = $this->request($reqParams, $this->params['orderApi']);
        if (!isset($result['result_code']) || $result['result_code'] != 'SUCCESS') {
            throw new PayException($result['err_code'] . ' ' . $result['err_code_des']);
        }

        // 唤起支付页
        $payLink = $result['mweb_url'] . '&redirect_url=' . urlencode($processor->appSchema);

        $resolveHash = explode('#', $processor->wapPaidGuide, 2);
        $resolveParam = explode('?', $resolveHash[0], 2);
        if (!empty($resolveParam[1])) {
            parse_str($resolveParam[1], $data);
        }
        $data['order_no'] = $orderNo;
        $resolveHash[0] = $resolveParam[0] . '?' . http_build_query($data);
        $guidePage = implode('#', $resolveHash);

        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set(
            'X-Unionsystem-Script',
            str_replace(
                ["\n", "\r"], '',
                Yii::$app->controller->render(
                    '@app/modules/pay/modules/v1/payments/h5wechat/libs/views/iframe',
                    ['iframeSrc' => $payLink, 'href' => $guidePage]
                )
            )
        );
    }

    /**
     * 接收支付回调
     * @return mixed|void
     * @throws PayException
     */
    public function callback()
    {
        // 获取操作类
        $action = Yii::$app->controller->action;

        // 附加失败事件
        Event::on($action::className(), $action::EVENT_CALLBACK_FAILURE, function () {
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->data = "<xml>
                <return_code><![CDATA[FAIL]]></return_code>
                <return_msg><![CDATA[ERROR]]></return_msg>
            </xml>";
        });

        // 接收解析参数
        $xmlContent = Yii::$app->request->getRawBody();
        libxml_disable_entity_loader(true);
        $receiveParams = json_decode(
            json_encode(simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA)),
            true
        );

        // 验证
        if ($receiveParams['return_code'] == 'SUCCESS') {
            if ($receiveParams['result_code'] == 'SUCCESS') {
                $getSign = $receiveParams['sign'];
                unset($receiveParams['sign']);
                ksort($receiveParams);
                if ($this->calSign($receiveParams) != $getSign) {
                    throw new PayException('验签失败');
                }
            } else {
                throw new PayException($receiveParams['err_code'] . ' ' . $receiveParams['err_code_des']);
            }
        } else {
            throw new PayException($receiveParams['return_msg']);
        }

        // 填充参数
        $this->xferNo = $receiveParams['out_trade_no']; // 商户支付订单号
        $this->xferAmt = bcdiv($receiveParams['total_fee'], 100, 2); // 交易支付金额
        $this->xferSn = $receiveParams['transaction_id']; // 服务商支付订单号

        // 附加成功事件
        Event::on($action::className(), $action::EVENT_CALLBACK_SUCCESS, function ($event) {
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->data = "<xml>
                <return_code><![CDATA[SUCCESS]]></return_code>
                <return_msg><![CDATA[{$event->data}]]></return_msg>
            </xml>";
        }, 'OK');
    }

    /**
     * 接口查询订单信息
     * @param $orderNo
     * @return mixed|void
     * @throws PayException
     * @throws \app\modules\order\modules\v1\exceptions\OrderException
     */
    public function query($orderNo)
    {
        // 订单数据
        $transfer = Transfer::findRowsByOrderNo($orderNo);
        $transfer = end($transfer);
        if (empty($transfer)) {
            throw new PayException('交易订单不存在');
        }

        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 请求参数
        $reqParams = [
            'appid' => isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
                $this->params['merchant'][$this->merchantId][$processor->channelAlias]['appId'] :
                $this->params['merchant'][$this->merchantId]['default']['appId'],
            'mch_id' => $this->merchantId,
            'out_trade_no' => $transfer['xfer_no'],
            'nonce_str' => md5(uniqid(microtime(), true)),
        ];

        // 接口请求
        $result = $this->request($reqParams, $this->params['queryApi']);

        // 填充参数
        $this->xferExt = $result;
        $this->xferAmt = $this->xferSn = false;
        $this->xferStat = Transfer::TO_BE_PAID;
        if (isset($result['total_fee'])) $this->xferAmt = $result['total_fee'];
        if (isset($result['transaction_id'])) $this->xferSn = $result['transaction_id'];
        if (isset($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
            $this->xferStat = Transfer::PAID;
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode('此订单已支付成功，无法再次支付');
            Yii::$app->response->data = Yii::$app->response->content = null;
        }
    }

    /**
     * 退款接口
     * @param $refundNo
     * @return mixed|void
     * @throws PayException
     */
    public function refund($refundNo)
    {
        // 订单数据
        $refund = Refund::findRowsByRefundNo($refundNo);
        $refund = end($refund);
        $transfer = Transfer::findRowsByRefundNo($refundNo);
        $transfer = end($transfer);

        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        try { // 查询退款订单

            $reqParams = [
                'appid' => isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
                    $this->params['merchant'][$this->merchantId][$processor->channelAlias]['appId'] :
                    $this->params['merchant'][$this->merchantId]['default']['appId'],
                'mch_id' => $this->merchantId,
                'nonce_str' => md5(uniqid(microtime(), true)),
                'out_refund_no' => $refund['refund_no'],
            ];

            $result = $this->request($reqParams, $this->params['refundQueryApi']);

            if (isset($result['refund_status_0']) && $result['refund_status_0'] == 'SUCCESS') {
                // 填充参数
                $this->refundSn = $result['refund_id_0'];
            } else {
                throw new PayException('退款请求查询到尚未成功');
            }

        } catch (\Exception $e) { // 退款请求

            $reqParams = [
                'appid' => isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
                    $this->params['merchant'][$this->merchantId][$processor->channelAlias]['appId'] :
                    $this->params['merchant'][$this->merchantId]['default']['appId'],
                'mch_id' => $this->merchantId,
                'nonce_str' => md5(uniqid(microtime(), true)),
                'out_trade_no' => $transfer['xfer_no'],
                'out_refund_no' => $refund['refund_no'],
                'total_fee' => bcmul($transfer['xfer_amt'], 100),
                'refund_fee' => bcmul($refund['refund_amt'], 100),
            ];

            $result = $this->request($reqParams, $this->params['refundApi'], true);
            if (!isset($result['result_code']) || $result['result_code'] != 'SUCCESS') {
                $errMessage = '微信 - 退款失败';
                if (isset($result['err_code']) && isset($result['err_code_des'])) {
                    $errMessage .= "[{$result['err_code']}]" . $result['err_code_des'];
                }
                throw new PayException($errMessage);
            }

            // 填充参数
            $this->refundSn = $result['refund_id'];
        }
    }
}