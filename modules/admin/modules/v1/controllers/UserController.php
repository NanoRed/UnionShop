<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use yii\helpers\Url;
use yii\filters\AccessControl;
use mdm\admin\models\searchs\User as UserSearch;
use app\modules\admin\modules\v1\models\forms\Signup;
use mdm\admin\models\User;
use mdm\admin\components\UserStatus;
use yii\base\UserException;

class UserController extends \mdm\admin\controllers\UserController
{
    /**
     * @var int 新增用户默认激活状态
     */
    public $userDefaultStatus;

    public function init()
    {
        parent::init();

        $this->viewPath = '@vendor/mdmsoft/yii2-admin/views/' . $this->id;

        // 注册默认为未激活
        $this->userDefaultStatus = UserStatus::INACTIVE;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs']['actions']['inactivate'] = ['post'];
        $behaviors['access'] = [
            'class' => AccessControl::className(),
            'except' => ['index', 'signup', 'activate', 'inactivate', 'delete', 'view'],
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('/user/index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionView($id)
    {
        return $this->render('/user/view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionSignup()
    {
        $model = new Signup();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                $redirectUrl = Url::toRoute('/admin/v1/user/index', true);
                return Yii::$app->getResponse()->redirect($redirectUrl);
            }
        }

        return $this->render('/user/signup', [
            'model' => $model,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionActivate($id)
    {
        /* @var $user User */
        $user = $this->findModel($id);
        if ($user->status == UserStatus::INACTIVE) {
            $user->status = UserStatus::ACTIVE;
            if ($user->save()) {
                $redirectUrl = Url::toRoute('/admin/v1/user/index', true);
                return Yii::$app->getResponse()->redirect($redirectUrl);
            } else {
                $errors = $user->firstErrors;
                throw new UserException(reset($errors));
            }
        }
        $redirectUrl = Url::toRoute('/admin/v1/user/index', true);
        return Yii::$app->getResponse()->redirect($redirectUrl);
    }

    /**
     * Inactivate user
     * @param $id
     * @return \yii\console\Response|\yii\web\Response
     * @throws UserException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionInactivate($id)
    {
        /* @var $user User */
        $user = $this->findModel($id);
        if ($user->status == UserStatus::ACTIVE) {
            $user->status = UserStatus::INACTIVE;
            if ($user->save()) {
                $redirectUrl = Url::toRoute('/admin/v1/user/index', true);
                return Yii::$app->getResponse()->redirect($redirectUrl);
            } else {
                $errors = $user->firstErrors;
                throw new UserException(reset($errors));
            }
        }
        $redirectUrl = Url::toRoute('/admin/v1/user/index', true);
        return Yii::$app->getResponse()->redirect($redirectUrl);
    }
}