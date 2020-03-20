<?php

namespace app\modules\market;

use yii\helpers\ArrayHelper;

/**
 * market module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\market\controllers';

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
                    'class' => 'app\modules\market\modules\v1\Module',
                ],
            ]
        ));
    }
}
