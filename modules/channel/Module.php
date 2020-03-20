<?php

namespace app\modules\channel;

use yii\helpers\ArrayHelper;

/**
 * channel module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\channel\controllers';

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
                    'class' => 'app\modules\channel\modules\v1\Module',
                ],
            ]
        ));
    }
}
