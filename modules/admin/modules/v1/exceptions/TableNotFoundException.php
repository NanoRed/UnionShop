<?php

namespace app\modules\admin\modules\v1\exceptions;

use yii\base\InvalidArgumentException;

/**
 * 表不存在异常
 * Class TableNotFoundException
 * @package app\modules\admin\modules\v1\exceptions
 */
class TableNotFoundException extends InvalidArgumentException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Table not Found';
    }
}
