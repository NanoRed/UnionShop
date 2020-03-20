<?php

namespace app\modules\pay\modules\v1\behaviors;

use yii\base\Behavior;
use yii\base\UnknownMethodException;

/**
 * 支付模块接口
 * Class PayResolve
 * @package app\modules\pay\modules\v1\behaviors
 */
class PayResolve extends Behavior
{
    /**
     * 支付订单号生成XferNo
     * 可使用order模块的OrderNumberMaker
     * @param string $prefix 生成订单的前缀
     * @param int $digit 生成订单号位数（不含前缀）
     * @return mixed
     *
     * e.g.
     * ```php
     * class CHANNEL extends Processor {
     *     public function payTransferOrderNumberGenerate()
     *     {
     *         $this->attachBehavior('orderNumberMaker', ['class' => OrderNumberMaker::className()]);
     *         return $this->getOrderNumber(__CLASS__ . $channel); // String类型
     *     }
     * }
     * ```
     */
    public function payTransferOrderNumberGenerate($prefix = null, $digit = null)
    {
        $methodName = __FUNCTION__;
        if ($this->owner->hasMethod($methodName, false)) {
            $params = [];
            if (isset($prefix)) $params[] = $prefix;
            if (isset($digit)) $params[] = $digit;
            return call_user_func_array([$this->owner, $methodName], $params);
        } else {
            throw new UnknownMethodException(
                get_class($this->owner) . PHP_EOL . '未实现' . PHP_EOL . $methodName . PHP_EOL . '公共方法'
            );
        }
    }

    /**
     * 退款订单号生成RefundNo
     * 可使用order模块的OrderNumberMaker
     * @param string $prefix 生成订单的前缀
     * @param int $digit 生成订单号位数（不含前缀）
     * @return mixed
     *
     * e.g.
     * ```php
     * class CHANNEL extends Processor {
     *     public function payRefundOrderNumberGenerate()
     *     {
     *         $this->attachBehavior('orderNumberMaker', ['class' => OrderNumberMaker::className()]);
     *         return $this->getOrderNumber(__CLASS__ . $channel); // String类型
     *     }
     * }
     * ```
     */
    public function payRefundOrderNumberGenerate($prefix = null, $digit = null)
    {
        $methodName = __FUNCTION__;
        if ($this->owner->hasMethod($methodName, false)) {
            $params = [];
            if (isset($prefix)) $params[] = $prefix;
            if (isset($digit)) $params[] = $digit;
            return call_user_func_array([$this->owner, $methodName], $params);
        } else {
            throw new UnknownMethodException(
                get_class($this->owner) . PHP_EOL . '未实现' . PHP_EOL . $methodName . PHP_EOL . '公共方法'
            );
        }
    }
}
