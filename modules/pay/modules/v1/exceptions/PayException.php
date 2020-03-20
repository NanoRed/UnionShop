<?php

namespace app\modules\pay\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 支付模块异常
 * 此模块异常信息请勿对用户展示
 * Class PayException
 * @package app\modules\pay\modules\v1\exceptions
 */
class PayException extends Exception
{
    public function getName()
    {
        return '支付模块（Module Pay）';
    }
}
