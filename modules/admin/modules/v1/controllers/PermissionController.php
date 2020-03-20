<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use mdm\admin\models\AuthItem;

class PermissionController extends \mdm\admin\controllers\PermissionController
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

        return $this->render('/permission/view', ['model' => $model]);
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
            return $this->render('/permission/create', ['model' => $model]);
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

        return $this->render('/permission/update', ['model' => $model]);
    }
}
