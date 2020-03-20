<?php

namespace app\modules\login\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 登陆模块异常
 * Class LoginException
 * @package app\modules\login\modules\v1\exceptions
 */
class LoginException extends Exception
{
    public function getName()
    {
        return '登陆模块（Module Login）';
    }
}
