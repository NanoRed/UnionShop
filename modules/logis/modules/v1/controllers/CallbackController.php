<?php

namespace app\modules\logis\modules\v1\controllers;

use Yii;
use yii\base\Controller;

/**
 * 接收回调
 * Class CallbackController
 * @package app\modules\logis\modules\v1\controllers
 */
class CallbackController extends Controller
{
    public function actions()
    {
        $actionMap = [];
        if ($route = strstr(Yii::$app->requestedRoute, Yii::$app->controller->id)) {
            $routeParts = explode('/', $route, 3);
            array_shift($routeParts);
            $className = str_replace('-', ' ', $routeParts[0]);
            $className = ucwords($className);
            $className = str_replace(' ', '', $className);
            $className = 'app\modules\logis\modules\v1\actions\callback\\' . $className . 'Action';
            $actionMap[implode('/', $routeParts)] = $className;
        }

        return $actionMap;
    }
}
