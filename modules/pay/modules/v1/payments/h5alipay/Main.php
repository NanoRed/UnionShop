<?php

namespace app\modules\pay\modules\v1\payments\h5alipay;

use Yii;
use yii\base\Event;
use yii\helpers\Url;
use app\modules\pay\modules\v1\bases\Payment;
use app\modules\order\modules\v1\models\Order;
use app\modules\order\modules\v1\models\OrderItem;
use app\modules\pay\modules\v1\models\Refund;
use app\modules\pay\modules\v1\models\Transfer;
use app\modules\pay\modules\v1\exceptions\PayException;

class Main extends Payment
{
    public function init()
    {
        parent::init();

        // alipay sdk
        require_once __DIR__ . '/libs/sdk/aop/AopCertClient.php';
        require_once __DIR__ . '/libs/sdk/aop/request/AlipayTradeWapPayRequest.php';
        require_once __DIR__ . '/libs/sdk/aop/request/AlipayTradeQueryRequest.php';
        require_once __DIR__ . '/libs/sdk/aop/request/AlipayTradeFastpayRefundQueryRequest.php';
        require_once __DIR__ . '/libs/sdk/aop/request/AlipayTradeRefundRequest.php';

        $this->params = require __DIR__ . '/libs/config/params.php';
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
                    '@app/modules/pay/modules/v1/payments/h5alipay/libs/views/location',
                    ['href' => $guidePage]
                )
            )
        );
    }

    /**
     * 唤起支付
     * @param $orderNo
     * @return mixed|void
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
        if (strlen($xferDesc) > 120) {
            $xferDesc = mb_substr(substr($xferDesc, 0, 110), 0, -1) . '...';
        }

        // 商户参数
        $merchantConfig = isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
            $this->params['merchant'][$this->merchantId][$processor->channelAlias] :
            $this->params['merchant'][$this->merchantId]['default'];

        // 载入参数
        $c = new \AopCertClient;
        $c->gatewayUrl = $merchantConfig['gatewayUrl'];
        $c->appId = $merchantConfig['appId'];
        $c->rsaPrivateKey = $merchantConfig['rsaPrivateKey'];
        $c->apiVersion = $merchantConfig['apiVersion'];
        $c->postCharset= $merchantConfig['postCharset'];
        $c->format = $merchantConfig['format'];
        $c->signType= $merchantConfig['signType'];
        $c->alipayrsaPublicKey = $c->getPublicKey($merchantConfig['alipayCertPath']); // 支付宝公钥证书中提取公钥
        $c->isCheckAlipayPublicCert = true; // 是否校验自动下载的支付宝公钥证书（如开启，要保证支付宝根证书在有效期内）
        $c->appCertSN = $c->getCertSN($merchantConfig['appCertPath']); //获取证书序列号
        $c->alipayRootCertSN = $c->getRootCertSN($merchantConfig['rootCertPath']); // 获取支付宝根证书序列号

        // 手机网站支付API
        $request = new \AlipayTradeWapPayRequest();
        $bizContent = [
            'body' => $xferDesc,
            'subject' => $processor->appName,
            'out_trade_no' => $this->xferNo,
            'timeout_express' => '15d',
            'total_amount' => $this->xferAmt,
            'product_code' => 'QUICK_WAP_WAY',
        ];
        $request->setBizContent(json_encode($bizContent, JSON_UNESCAPED_UNICODE)); // 设置请求参数
        $resolveHash = explode('#', $processor->wapPaidGuide, 2);
        $resolveParam = explode('?', $resolveHash[0], 2);
        if (!empty($resolveParam[1])) {
            parse_str($resolveParam[1], $data);
        }
        $data['order_no'] = $orderNo;
        $resolveHash[0] = $resolveParam[0] . '?' . http_build_query($data);
        $guidePage = implode('#', $resolveHash);
        $renderHtml = '<script type="text/javascript">top.location.href="' . $guidePage . '";</script>';
        $returnUrl = Url::toRoute( // 因为支付宝网关页面在iframe打开的，使用top.location.href跳转
            '/pay/v1/callback/render/' . Yii::$app->security->maskToken($renderHtml), true
        );
        $request->setReturnUrl($returnUrl); // 支付完毕跳转页面
        $notifyUrl = Url::toRoute(
            '/pay/v1/callback/payment/' .
            Yii::$app->security->maskToken(implode(',', [$processor->channelAlias, $this->accountId])),
            true
        );
        $request->setNotifyUrl($notifyUrl); // 设置支付通知回调地址

        // 获取携参网关地址
        $payLink = $c->pageExecute($request, 'GET');

        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set(
            'X-Unionsystem-Script',
            str_replace(
                ["\n", "\r"], '',
                Yii::$app->controller->render(
                    '@app/modules/pay/modules/v1/payments/h5alipay/libs/views/iframe', ['iframeSrc' => $payLink]
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
        Event::on($action::className(), $action::EVENT_CALLBACK_FAILURE, function ($event) {
            Yii::$app->response->statusCode = 200;
            // Yii::$app->response->data = $event->errorMessage;
            Yii::$app->response->data = 'failure';
        });

        // 接收参数
        $receiveParams = $_POST;

        // 渠道实例
        $processor = Yii::$app->getModule('channel/v1')->processor;

        // 商户参数
        $merchantConfig = isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
            $this->params['merchant'][$this->merchantId][$processor->channelAlias] :
            $this->params['merchant'][$this->merchantId]['default'];

        // 验证
        $c = new \AopCertClient;
        $c->alipayrsaPublicKey = $c->getPublicKey($merchantConfig['alipayCertPath']); // 支付宝公钥证书中提取公钥
        $valid = $c->rsaCheckV1($receiveParams, '', $receiveParams['sign_type']);
        if (!$valid) {
            throw new PayException('验签失败');
        }
        if ($receiveParams['app_id'] != $merchantConfig['appId']) {
            throw new PayException('错误应用');
        }

        // 填充参数
        $this->xferNo = $receiveParams['out_trade_no']; // 商户支付订单号
        $this->xferAmt = $receiveParams['total_amount']; // 交易支付金额
        $this->xferSn = $receiveParams['trade_no']; // 服务商支付订单号

        // 附加成功事件
        Event::on($action::className(), $action::EVENT_CALLBACK_SUCCESS, function () {
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->data = "success";
        });
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

        // 商户参数
        $merchantConfig = isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
            $this->params['merchant'][$this->merchantId][$processor->channelAlias] :
            $this->params['merchant'][$this->merchantId]['default'];

        // 载入参数
        $c = new \AopCertClient;
        $c->gatewayUrl = $merchantConfig['gatewayUrl'];
        $c->appId = $merchantConfig['appId'];
        $c->rsaPrivateKey = $merchantConfig['rsaPrivateKey'];
        $c->apiVersion = $merchantConfig['apiVersion'];
        $c->postCharset= $merchantConfig['postCharset'];
        $c->format = $merchantConfig['format'];
        $c->signType= $merchantConfig['signType'];
        $c->alipayrsaPublicKey = $c->getPublicKey($merchantConfig['alipayCertPath']); // 支付宝公钥证书中提取公钥
        $c->isCheckAlipayPublicCert = true; // 是否校验自动下载的支付宝公钥证书（如开启，要保证支付宝根证书在有效期内）
        $c->appCertSN = $c->getCertSN($merchantConfig['appCertPath']); //获取证书序列号
        $c->alipayRootCertSN = $c->getRootCertSN($merchantConfig['rootCertPath']); // 获取支付宝根证书序列号

        // 统一收单线下交易查询
        $request = new \AlipayTradeQueryRequest();
        $bizContent = ['out_trade_no' => $transfer['xfer_no']];
        $request->setBizContent(json_encode($bizContent, JSON_UNESCAPED_UNICODE)); // 设置请求参数

        // 获取返回
        $result = (array)($c->execute($request));
        if (!isset($result['alipay_trade_query_response']['code']) ||
            $result['alipay_trade_query_response']['code'] != '10000') {
            $errorMessage = '未知请求错误';
            if (!empty($result['alipay_trade_query_response']['msg'])) {
                $errorMessage = "[{$result['alipay_trade_query_response']['code']}]";
                $errorMessage .= $result['alipay_trade_query_response']['msg'];
            }
            throw new PayException($errorMessage);
        } else {
            $result = $result['alipay_trade_query_response'];
        }

        // 填充参数
        $this->xferExt = $result;
        $this->xferAmt = $this->xferSn = false;
        $this->xferStat = Transfer::TO_BE_PAID;
        if (isset($result['total_amount'])) $this->xferAmt = $result['total_amount'];
        if (isset($result['trade_no'])) $this->xferSn = $result['trade_no'];
        if (isset($result['trade_status']) && $result['trade_status'] == 'TRADE_SUCCESS') {
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

        // 商户参数
        $merchantConfig = isset($this->params['merchant'][$this->merchantId][$processor->channelAlias]) ?
            $this->params['merchant'][$this->merchantId][$processor->channelAlias] :
            $this->params['merchant'][$this->merchantId]['default'];

        // 载入参数
        $c = new \AopCertClient;
        $c->gatewayUrl = $merchantConfig['gatewayUrl'];
        $c->appId = $merchantConfig['appId'];
        $c->rsaPrivateKey = $merchantConfig['rsaPrivateKey'];
        $c->apiVersion = $merchantConfig['apiVersion'];
        $c->postCharset= $merchantConfig['postCharset'];
        $c->format = $merchantConfig['format'];
        $c->signType= $merchantConfig['signType'];
        $c->alipayrsaPublicKey = $c->getPublicKey($merchantConfig['alipayCertPath']); // 支付宝公钥证书中提取公钥
        $c->isCheckAlipayPublicCert = true; // 是否校验自动下载的支付宝公钥证书（如开启，要保证支付宝根证书在有效期内）
        $c->appCertSN = $c->getCertSN($merchantConfig['appCertPath']); //获取证书序列号
        $c->alipayRootCertSN = $c->getRootCertSN($merchantConfig['rootCertPath']); // 获取支付宝根证书序列号

        try { // 查询退款订单

            // 统一收单交易退款查询
            $request = new \AlipayTradeFastpayRefundQueryRequest();
            $bizContent = [
                'out_trade_no' => $transfer['xfer_no'],
                'out_request_no' => $refund['refund_no'],
            ];
            $request->setBizContent(json_encode($bizContent, JSON_UNESCAPED_UNICODE)); // 设置请求参数

            // 获取返回
            $result = (array)($c->execute($request));

            if (isset($result['alipay_trade_fastpay_refund_query_response']['refund_amount']) &&
                (
                    empty($result['alipay_trade_fastpay_refund_query_response']['refund_status']) ||
                    $result['alipay_trade_fastpay_refund_query_response']['refund_status'] == 'REFUND_SUCCESS'
                )
            ) {
                // 填充参数
                if (empty($result['alipay_trade_fastpay_refund_query_response']['refund_settlement_id'])) {
                    $this->refundSn = '';
                } else {
                    $this->refundSn = $result['alipay_trade_fastpay_refund_query_response']['refund_settlement_id'];
                }
            } else {
                throw new PayException('退款请求查询到尚未成功');
            }

        } catch (\Exception $e) { // 退款请求

            // 统一收单交易退款接口
            $request = new \AlipayTradeRefundRequest();
            $bizContent = [
                'out_trade_no' => $transfer['xfer_no'],
                'refund_amount' => $refund['refund_amt'],
                'out_request_no' => $refund['refund_no'],
            ];
            $request->setBizContent(json_encode($bizContent, JSON_UNESCAPED_UNICODE)); // 设置请求参数

            // 获取返回
            $result = (array)($c->execute($request));
            if (!isset($result['alipay_trade_refund_response']['code']) ||
                $result['alipay_trade_refund_response']['code'] != '10000') {
                $errMessage = '支付宝 - 退款失败';
                if (isset($result['alipay_trade_refund_response']['code']) &&
                    isset($result['alipay_trade_refund_response']['msg'])) {
                    $errMessage .= "[{$result['alipay_trade_refund_response']['code']}]";
                    $errMessage .= $result['alipay_trade_refund_response']['msg'];
                }
                if (isset($result['alipay_trade_refund_response']['sub_code']) &&
                    isset($result['alipay_trade_refund_response']['sub_msg'])) {
                    $errMessage .= "({$result['alipay_trade_refund_response']['sub_code']}:";
                    $errMessage .= "{$result['alipay_trade_refund_response']['sub_msg']})";
                }
                throw new PayException($errMessage);
            }

            // 填充参数
            $this->refundExt = $result;
            if (empty($result['alipay_trade_refund_response']['refund_settlement_id'])) {
                $this->refundSn = '';
            } else {
                $this->refundSn = $result['alipay_trade_refund_response']['refund_settlement_id'];
            }
        }
    }
}