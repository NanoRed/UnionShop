<?php

namespace app\modules\user\modules\v1\bases;

use Yii;
use yii\filters\VerbFilter;
use yii\filters\auth\HttpHeaderAuth;
use yii\web\UnauthorizedHttpException;

/**
 * 验证身份权限继承类
 * Class AuthController
 * @package app\modules\user\modules\v1\bases
 */
class AuthController extends CorsController
{
    public function init()
    {
        parent::init();

        Yii::$app->user->identityClass = 'app\modules\user\modules\v1\models\User';
        Yii::$app->user->enableAutoLogin = false;
        Yii::$app->user->enableSession = false;
        Yii::$app->user->loginUrl = null;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = [ // 只允许post请求
            'class' => VerbFilter::className(),
            'actions' => [
                '*'  => ['post'],
            ],
        ];
        $behaviors['authenticator'] = [ // X-Api-Key验证
            'class' => HttpHeaderAuth::className(),
        ];

        return $behaviors;
    }

    public function runAction($id, $params = [])
    {
        try {
            return parent::runAction($id, $params);
        } catch (UnauthorizedHttpException $e) {
            Yii::$app->getModule('login/v1')->runAction('call/login');
            Yii::$app->response->statusCode = 401;
            Yii::$app->response->statusText = urlencode("身份验证失败或过期，请重新登陆");
            Yii::$app->response->data = Yii::$app->response->content = null;
        }
    }
}
