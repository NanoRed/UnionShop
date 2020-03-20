<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use mdm\admin\components\Configs;
use app\modules\admin\modules\v1\rbac\Channel as Item;
use app\modules\admin\modules\v1\models\AuthItem;
use yii\web\NotFoundHttpException;

class RoleController extends \mdm\admin\controllers\RoleController
{
    /**
     * @inheritdoc
     */
    public function getViewPath()
    {
        return '@vendor/mdmsoft/yii2-admin/views/item';
    }

    /**
     * @inheritdoc
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('/role/view', ['model' => $model]);
    }

    /**
     * @inheritdoc
     */
    public function actionCreate()
    {
        $model = new AuthItem(null);
        $model->type = $this->type;
        if ($model->load(Yii::$app->getRequest()->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->name]);
        } else {
            return $this->render('/role/create', ['model' => $model]);
        }
    }

    /**
     * @inheritdoc
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->getRequest()->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->name]);
        }

        return $this->render('/role/update', ['model' => $model]);
    }

    /**
     * @inheritdoc
     */
    protected function findModel($id)
    {
        $auth = Configs::authManager();
        /* @var $auth \app\modules\admin\modules\v1\rbac\DbManager */
        switch ($this->type) {
            case Item::TYPE_ROLE:
                $item = $auth->getRole($id);
                break;
            case Item::TYPE_CHANNEL:
                $item = $auth->getChannel($id);
                break;
            default:
                $item = $auth->getPermission($id);
        }
        if ($item) {
            return new AuthItem($item);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
