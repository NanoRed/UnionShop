<?php

namespace app\modules\rewrite;

use yii\helpers\ArrayHelper;

/**
 * rewrite module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\rewrite\controllers';

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
                    'class' => 'app\modules\rewrite\modules\v1\Module',
                ],
            ]
        ));
    }
}
