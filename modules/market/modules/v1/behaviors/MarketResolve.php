<?php

namespace app\modules\market\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * 营销模块接口
 * Class MarketResolve
 * @package app\modules\market\modules\v1\behaviors
 */
class MarketResolve extends Behavior
{
    /**
     * 关于$afterExec实现说明：
     * 可能你会看到部分营销工具对应函数返回值会如下所示，
     * ```php
     * public function marketMethod($param)
     * {
     *     $index = 1; // 执行顺序，值越小越先执行，注意值不要重复
     *     $func = function ($func) use ($param) {
     *         return function ($isExec = true) use ($func, $param) {
     *             if ($isExec) {
     *                 #code
     *             }
     *             $func();
     *         }
     *     };
     *     return [$index => $func];
     * }
     * ```
     * $afterExec的值为这些营销工具返回合并的数组
     * 其中$index代表执行顺序级（顺数顺序，按123456...地执行），$func代表连续执行中的其中一个逻辑闭包，执行时将如下，
     * ```php
     * krsort($afterExec);
     * $do = function () {};
     * foreach ($afterExec as $func) {
     *     $do = $func($do);
     * }
     * $do();
     * ```
     * 即将闭包嵌套起来后执行，而其中闭包中参数$isExec可控制下个节点的逻辑代码是否执行，起到顺序节点间联系调节作用
     * 若想跳过下个节点的执行，可如下编写，
     * public function marketMethod($param)
     * {
     *     $index = 1; // 执行顺序，值越小越先执行，注意值不要重复
     *     $func = function ($func) use ($param) {
     *         return function ($isExec = true) use ($func, $param) {
     *             if ($isExec) {
     *               * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - *
     *               - if ($error = true) {                                                      -
     *               -     $func(false); return; // 这样可控制此闭包函数执行至此，不继续往下执行 -
     *               - }                                                                         -
     *               * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - *
     *                 #code
     *             }
     *             $func();
     *         }
     *     };
     *     return [$index => $func];
     * }
     * ```
     */

    public $enableTools = []; // 开启的营销工具

    /**
     * 购物车商品营销工具验证
     * @param array $good 商品详情
     * @param array $cartItemsProperty 购物车商品属性
     * @throws InvalidConfigException
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        $afterExec = [];
        $methodName = __FUNCTION__;
        foreach ($this->enableTools as $i => $validator) {
            if (method_exists($validator, $methodName)) {
                if (!$validator instanceof Behavior) {
                    $this->enableTools[$i] = $validator = Yii::createObject($validator);
                    if (!$validator instanceof Behavior) {
                        throw new InvalidConfigException(get_class($validator) . '未继承yii\base\Behavior');
                    }
                    $validator->attach($this);
                }

                $return = $validator->$methodName($good, $cartItemsProperty);
                if (is_array($return)) {
                    $afterExec = ArrayHelper::merge($afterExec, $return);
                }
            }
        }
        if (!empty($afterExec)) {
            krsort($afterExec); // 注意执行顺序设置
            $do = function () {};
            foreach ($afterExec as $func) {
                $do = $func($do);
            }
            $do();
        }
    }

    /**
     * 订单详情营销工具调整
     * @param array $orders 渠道订单表数据
     * @param array $orderItems 渠道订单商品表数据
     * @param array $orderShipping 渠道订单送货表数据
     * @return array
     * @throws InvalidConfigException
     */
    public function marketOrderAdjust($orders, $orderItems, $orderShipping)
    {
        $afterExec = [];
        $methodName = __FUNCTION__;
        foreach ($this->enableTools as $i => $validator) {
            if (method_exists($validator, $methodName)) {
                if (!$validator instanceof Behavior) {
                    $this->enableTools[$i] = $validator = Yii::createObject($validator);
                    if (!$validator instanceof Behavior) {
                        throw new InvalidConfigException(get_class($validator) . '未继承yii\base\Behavior');
                    }
                    $validator->attach($this);
                }

                $return = $validator->$methodName($orders, $orderItems, $orderShipping);
                if (is_array($return)) {
                    $afterExec = ArrayHelper::merge($afterExec, $return);
                }
            }
        }
        if (!empty($afterExec)) {
            krsort($afterExec); // 注意执行顺序设置
            $do = function () {};
            foreach ($afterExec as $func) {
                $do = $func($do);
            }
            $do();
        }

        return [$orders, $orderItems, $orderShipping];
    }

    /**
     * 接收支付通知时的营销工具执行
     * @param string|array $orderNo 订单号
     * @throws InvalidConfigException
     */
    public function marketGetPaymentNotification($orderNo)
    {
        $afterExec = [];
        $methodName = __FUNCTION__;
        foreach ($this->enableTools as $i => $validator) {
            if (method_exists($validator, $methodName)) {
                if (!$validator instanceof Behavior) {
                    $this->enableTools[$i] = $validator = Yii::createObject($validator);
                    if (!$validator instanceof Behavior) {
                        throw new InvalidConfigException(get_class($validator) . '未继承yii\base\Behavior');
                    }
                    $validator->attach($this);
                }

                $return = $validator->$methodName($orderNo);
                if (is_array($return)) {
                    $afterExec = ArrayHelper::merge($afterExec, $return);
                }
            }
        }
        if (!empty($afterExec)) {
            krsort($afterExec); // 注意执行顺序设置
            $do = function () {};
            foreach ($afterExec as $func) {
                $do = $func($do);
            }
            $do();
        }
    }

    /**
     * 取消订单时恢复资源
     * @param $orderNo
     * @throws InvalidConfigException
     */
    public function marketOrderCancel($orderNo)
    {
        $afterExec = [];
        $methodName = __FUNCTION__;
        foreach ($this->enableTools as $i => $validator) {
            if (method_exists($validator, $methodName)) {
                if (!$validator instanceof Behavior) {
                    $this->enableTools[$i] = $validator = Yii::createObject($validator);
                    if (!$validator instanceof Behavior) {
                        throw new InvalidConfigException(get_class($validator) . '未继承yii\base\Behavior');
                    }
                    $validator->attach($this);
                }

                $return = $validator->$methodName($orderNo);
                if (is_array($return)) {
                    $afterExec = ArrayHelper::merge($afterExec, $return);
                }
            }
        }
        if (!empty($afterExec)) {
            krsort($afterExec); // 注意执行顺序设置
            $do = function () {};
            foreach ($afterExec as $func) {
                $do = $func($do);
            }
            $do();
        }
    }
}
