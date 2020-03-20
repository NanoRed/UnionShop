<?php

namespace app\modules\pay\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;

class PaymentAccount extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%payment_accounts}}';
    }

    public function getPayment()
    {
        return $this->hasOne(Payment::className(), ['id' => 'payment_id']);
    }

    public static $register; // 寄存器

    /**
     * 释放寄存器
     */
    public static function clearRegister()
    {
        static::$register = null;
    }

    /**
     * 查询支付账户对应的支付方式
     * @param $paymentAccountId
     * @return mixed
     */
    public static function findPaymentByAccountId($paymentAccountId)
    {
        if (!isset(static::$register[$paymentAccountId])) {
            $key = Yii::$app->id . md5(__METHOD__ . $paymentAccountId);
            $paymentAccount = json_decode(Yii::$app->redis->get($key), true);
            if (empty($paymentAccount)) {
                $paymentAccount = static::find()
                    ->where(['id' => $paymentAccountId])
                    ->with('payment')
                    ->asArray()
                    ->one();
                Yii::$app->redis->setex($key, 3600, json_encode($paymentAccount)); // 缓存一小时
            }
            static::$register[$paymentAccountId] = $paymentAccount;
        }

        return static::$register[$paymentAccountId];
    }
}
