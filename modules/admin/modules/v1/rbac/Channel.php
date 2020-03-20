<?php

namespace app\modules\admin\modules\v1\rbac;

use yii\rbac\Item;

class Channel extends Item
{
    const TYPE_CHANNEL = 3;

    /**
     * {@inheritdoc}
     */
    public $type = self::TYPE_CHANNEL;
}
