<?php

namespace app\modules\login\modules\v1;

/**
 * login_v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\login\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
        $this->controllerMap['callback'] = 'app\modules\login\modules\v1\controllers\CallbackController';
    }
}
