<?php

namespace app\modules\item\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 商品模块异常
 * Class ItemException
 * @package app\modules\item\modules\v1\exceptions
 */
class ItemException extends Exception
{
    public function getName()
    {
        return '商品模块（Module Item）';
    }
}
