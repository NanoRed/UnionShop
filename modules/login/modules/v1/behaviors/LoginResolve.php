<?php

namespace app\modules\login\modules\v1\behaviors;

use yii\base\Behavior;
use yii\base\UnknownMethodException;

/**
 * 登陆模块接口
 * Class LoginResolve
 * @package app\modules\login\modules\v1\behaviors
 */
class LoginResolve extends Behavior
{
    /**
     * 调起登陆
     * @return mixed
     */
    public function loginCall()
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

    /**
     * 渠道登陆完成回调
     * @return mixed
     */
    public function loginCallback()
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
