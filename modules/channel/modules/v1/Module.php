<?php

namespace app\modules\channel\modules\v1;

use Yii;
use app\modules\channel\modules\v1\exceptions\ChannelException;

/**
 * channel_v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\channel\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }

    /**
     * 获取渠道业务内核单例
     * @return object|null
     * @throws ChannelException
     * @throws \yii\base\InvalidConfigException
     */
    public function getProcessor()
    {
        if (!$this->has('processor')) {
            $channel = Yii::$app->request->headers->get('X-Unionsystem-Channel');
            if (empty($channel)) {
                throw new ChannelException('请求头X-Unionsystem-Channel缺失');
            }
            $this->setProcessor($channel);
        }

        return $this->get('processor');
    }

    /**
     * 设置渠道业务内核单例
     * @param $processor
     * @throws \yii\base\InvalidConfigException
     */
    public function setProcessor($processor)
    {
        if (!empty($processor) && is_string($processor)) {
            $processor = ['class' => __NAMESPACE__ . '\processors\\' . strtolower($processor) . '\Main'];
        }

        $this->set('processor', $processor);
    }
}
