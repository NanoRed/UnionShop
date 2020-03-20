<?php

namespace app\modules\order;

use yii\helpers\ArrayHelper;

/**
 * order module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\order\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
        $this->setModules(ArrayHelper::merge(
            $this->getModules(),
            [
                'v1' => [
                    'class' => 'app\modules\order\modules\v1\Module',
                ],
                'v1-console' => [
                    'class' => 'app\modules\order\modules\v1\Module',
                    'controllerNamespace' => 'app\modules\order\modules\v1\commands',
                ],
            ]
        ));
    }
}
