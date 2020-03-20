<?php

namespace app\modules\order\modules\v1;

use yii\filters\Cors;
use yii\helpers\ArrayHelper;

/**
 * order_v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\order\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }
}
