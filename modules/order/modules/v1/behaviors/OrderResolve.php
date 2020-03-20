<?php

namespace app\modules\order\modules\v1\behaviors;

use yii\base\Behavior;
use yii\base\UnknownMethodException;

/**
 * 订单模块接口
 * Class OrderResolve
 * @package app\modules\order\modules\v1\behaviors
 */
class OrderResolve extends Behavior
{
    /**
     * 订单号生成OrderNo
     * 可使用order模块的OrderNumberMaker
     * @param string $prefix 生成订单的前缀
     * @param int $digit 生成订单号位数（不含前缀）
     * @return mixed
     *
     * e.g.
     * ```php
     * class CHANNEL extends Processor {
     *     public function orderNumberGenerate()
     *     {
     *         $this->attachBehavior('orderNumberMaker', ['class' => OrderNumberMaker::className()]);
     *         return $this->getOrderNumber(__CLASS__ . $channel); // String类型
     *     }
     * }
     * ```
     */
    public function orderNumberGenerate($prefix = null, $digit = null)
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
     * 读取购物车商品
     * 暂使用rewrite模块的CartItemBox
     * 往后所有业务重写完毕将不使用rewrite模块
     * @return mixed
     *
     * e.g.
     * ```php
     * class CHANNEL extends Processor {
     *     public function orderCartItemExtract()
     *     {
     *         $this->attachBehavior('cartItemBox', ['class' => CartItemBox::className()]);
     *         return $this->getOrderItems(); // Array类型
     *     }
     * }
     * ```
     */
    public function orderCartItemExtract()
    {
        $methodName = __FUNCTION__;
        if ($this->owner->hasMethod($methodName, false)) {
            return $this->owner->$methodName();
        } else {
            throw new UnknownMethodException(
                get_class($this->owner) . PHP_EOL . '未实现' . PHP_EOL . $methodName . PHP_EOL . '公共方法'
            );
        }
    }
}
