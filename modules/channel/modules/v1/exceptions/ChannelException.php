<?php

namespace app\modules\channel\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 渠道模块异常
 * Class ChannelException
 * @package app\modules\channel\modules\v1\exceptions
 */
class ChannelException extends Exception
{
    public function getName()
    {
        return '渠道模块（Module Channel）';
    }
}
