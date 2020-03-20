<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use yii\helpers\Url;
use yii\filters\AccessControl;
use mdm\admin\models\form\Login;
use mdm\admin\models\form\ChangePassword;

class AccountController extends \mdm\admin\controllers\UserController
{
    public function init()
    {
        parent::init();

        $this->viewPath = '@vendor/mdmsoft/yii2-admin/views/' . $this->id;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['verbs']['actions']['logout']);
        $behaviors['access'] = [
            'class' => AccessControl::className(),
            'except' => ['login', 'logout', 'change-password'],
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actionLogin()
    {
        if (!Yii::$app->getUser()->isGuest) {
            return $this->goHome();
        }

        $model = new Login();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->login()) {
            return $this->goHome();
        } else {
            return $this->render('/account/login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function actionChangePassword()
    {
        $model = new ChangePassword();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->change()) {
            $redirectUrl = Url::toRoute('/admin/v1/account/logout', true);
            return $this->render('/account/change-password-successfully', [
                'redirectUrl' => $redirectUrl
            ]);
        }

        return $this->render('/account/change-password', [
            'model' => $model,
        ]);
    }
}