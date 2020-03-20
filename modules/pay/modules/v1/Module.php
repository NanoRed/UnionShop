<?php

namespace app\modules\pay\modules\v1;

use Yii;
use app\modules\pay\modules\v1\models\PaymentAccount;
use app\modules\pay\modules\v1\models\ChannelPayment;
use app\modules\pay\modules\v1\exceptions\PayException;

/**
 * pay_v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\pay\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
        $this->controllerMap['callback'] = 'app\modules\pay\modules\v1\controllers\CallbackController';
    }

    /**
     * 获取支付方式类单例
     * @param $paymentAccountId
     * @return object|null
     * @throws PayException
     * @throws \yii\base\InvalidConfigException
     */
    public function getPayment($paymentAccountId)
    {
        if (!$this->has('payment' . $paymentAccountId)) {
            $channelId = Yii::$app->getModule('channel/v1')->processor->channelId;
            $channelPaymentAccount = ChannelPayment::findChannelPaymentAccount($channelId);
            if (!in_array($paymentAccountId, $channelPaymentAccount)) {
                throw new PayException('渠道不存在的支付账户');
            }
            $paymentAccount = PaymentAccount::findPaymentByAccountId($paymentAccountId);
            if (is_null($paymentAccount) || is_null($paymentAccount['payment'])) {
                throw new PayException('错误的支付账户');
            }
            $paymentAlias = strtolower($paymentAccount['payment']['alias']);
            $payment = [
                'class' => __NAMESPACE__ . '\payments\\' . $paymentAlias . '\Main',
                'paymentId' => $paymentAccount['payment']['id'],
                'paymentAlias' => $paymentAlias,
                'accountId' => $paymentAccount['id'],
                'merchantId' => $paymentAccount['merchant_id'],
            ];
            $this->set('payment' . $paymentAccountId, $payment);
        }

        return $this->get('payment' . $paymentAccountId);
    }
}
