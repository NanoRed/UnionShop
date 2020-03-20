<?php

namespace app\modules\user\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 用户模块异常
 * Class UserException
 * @package app\modules\user\modules\v1\exceptions
 */
class UserException extends Exception
{
    public function getName()
    {
        return '用户模块（Module User）';
    }
}
