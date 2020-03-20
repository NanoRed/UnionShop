<?php

namespace app\modules\logis;

use yii\helpers\ArrayHelper;

/**
 * logis module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\logis\controllers';

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
                    'class' => 'app\modules\logis\modules\v1\Module',
                ],
                'v1-console' => [
                    'class' => 'app\modules\logis\modules\v1\Module',
                    'controllerNamespace' => 'app\modules\logis\modules\v1\commands',
                ],
            ]
        ));
    }
}
