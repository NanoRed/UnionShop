<?php

namespace app\modules\market\modules\v1\exceptions;

use yii\base\Exception;

/**
 * 营销模块异常
 * Class MarketException
 * @package app\modules\market\modules\v1\exceptions
 */
class MarketException extends Exception
{
    public function getName()
    {
        return '营销模块（Module Market）';
    }
}
