<?php

namespace app\modules\order\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 订单模块异常
 * Class OrderException
 * @package app\modules\order\modules\v1\exceptions
 */
class OrderException extends Exception
{
    public function getName()
    {
        return '订单模块（Module Order）';
    }
}
