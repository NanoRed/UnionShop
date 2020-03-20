<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use mdm\admin\models\Menu;
use mdm\admin\components\Helper;

class MenuController extends \mdm\admin\controllers\MenuController
{
    public function init()
    {
        parent::init();

        $this->setViewPath('@vendor/mdmsoft/yii2-admin/views/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function actionView($id)
    {
        return $this->render('/menu/view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionCreate()
    {
        $model = new Menu;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Helper::invalidate();
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('/menu/create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->menuParent) {
            $model->parent_name = $model->menuParent->name;
        }
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Helper::invalidate();
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('/menu/update', [
                'model' => $model,
            ]);
        }
    }
}
