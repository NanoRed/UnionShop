<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

/**
 * 错误显示类
 * Class ErrorController
 * @package app\controllers
 */
class ErrorController extends Controller
{
    /**
     * 原生404
     * @return \yii\console\Response|\yii\web\Response
     */
    public function actionRaw404()
    {
        Yii::$app->response->statusCode = 404;
        return Yii::$app->response;
    }
}
