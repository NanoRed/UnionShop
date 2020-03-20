<?php

namespace app\modules\rewrite\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 待重写系统模块异常
 * Class RewriteException
 * @package app\modules\rewrite\modules\v1\exceptions
 */
class RewriteException extends Exception
{
    public function getName()
    {
        return '待重写系统模块（Module Rewrite）';
    }
}
