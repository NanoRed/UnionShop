<?php

namespace app\modules\logis\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 物流模块异常
 * Class LogisException
 * @package app\modules\logis\modules\v1\exceptions
 */
class LogisException extends Exception
{
    public function getName()
    {
        return '物流模块（Module Logis）';
    }
}
