<?php

namespace app\modules\logis\modules\v1;

/**
 * logis_v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\logis\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
        $this->controllerMap['callback'] = 'app\modules\logis\modules\v1\controllers\CallbackController';
    }

    /**
     * 获取ERP单例
     * @param $alias
     * @return object|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getErp($alias)
    {
        if (!$this->has('erp' . $alias)) {
            $class = [
                'class' => __NAMESPACE__ . '\erps\\' . $alias . '\Main',
            ];
            $this->set('erp' . $alias, $class);
        }

        return $this->get('erp' . $alias);
    }

    /**
     * 获取Track单例
     * @param $alias
     * @return object|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getTrack($alias)
    {
        if (!$this->has('track' . $alias)) {
            $class = [
                'class' => __NAMESPACE__ . '\tracks\\' . $alias . '\Main',
            ];
            $this->set('track' . $alias, $class);
        }

        return $this->get('track' . $alias);
    }
}
