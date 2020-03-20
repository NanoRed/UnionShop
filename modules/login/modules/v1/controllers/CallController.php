<?php

namespace app\modules\login\modules\v1\controllers;

use Yii;
use app\modules\user\modules\v1\bases\CorsController;

/**
 * 调用唤起
 * Class CallController
 * @package app\modules\login\modules\v1\controllers
 */
class CallController extends CorsController
{
    /**
     * 唤起登陆
     */
    public function actionLogin()
    {
        return Yii::$app->getModule('channel/v1')->processor->getBehavior('login')->loginCall();
    }
}
