<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use app\modules\admin\modules\v1\models\Assignment;
use yii\web\NotFoundHttpException;

class AssignmentController extends \mdm\admin\controllers\AssignmentController
{
    public function init()
    {
        parent::init();

        $this->viewPath = '@vendor/mdmsoft/yii2-admin/views/' . $this->id;
        $this->userClassName = 'mdm\admin\models\User';
        $this->idField = 'id';
    }

    /**
     * @inheritdoc
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('/assignment/view', [
            'model' => $model,
            'idField' => $this->idField,
            'usernameField' => $this->usernameField,
            'fullnameField' => $this->fullnameField,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionAssign($id)
    {
        $items = Yii::$app->getRequest()->post('items', []);
        $model = new Assignment($id);
        $success = $model->assign($items);
        Yii::$app->getResponse()->format = 'json';
        return array_merge($model->getItems(), ['success' => $success]);
    }

    /**
     * @inheritdoc
     */
    public function actionRevoke($id)
    {
        $items = Yii::$app->getRequest()->post('items', []);
        $model = new Assignment($id);
        $success = $model->revoke($items);
        Yii::$app->getResponse()->format = 'json';
        return array_merge($model->getItems(), ['success' => $success]);
    }

    /**
     * @inheritdoc
     */
    protected function findModel($id)
    {
        $class = $this->userClassName;
        if (($user = $class::findIdentity($id)) !== null) {
            return new Assignment($id, $user);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
