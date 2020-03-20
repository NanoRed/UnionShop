<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use mdm\admin\components\Configs;
use mdm\admin\components\ItemController;
use app\modules\admin\modules\v1\rbac\Channel as Item;
use app\modules\admin\modules\v1\models\AuthItem;
use app\modules\admin\modules\v1\models\Channel;
use app\modules\admin\modules\v1\models\searchs\AuthItem as AuthItemSearch;
use app\modules\admin\modules\v1\components\ChannelHelper;
use yii\web\NotFoundHttpException;

class ChannelController extends ItemController
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
    public function labels()
    {
        return[
            'Item' => '渠道权限',
            'Items' => '渠道权限列表',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return Item::TYPE_CHANNEL;
    }

    /**
     * @inheritdoc
     */
    public function actionIndex()
    {
        $searchModel = new AuthItemSearch(['type' => $this->type]);
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        return $this->render('/channel/index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('/channel/view', ['model' => $model]);
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
            $dataDropDownList = Channel::findAllChannels();
            $dataDropDownList = array_column($dataDropDownList, 'name', 'id');
            return $this->render('/channel/create', ['model' => $model, 'dataDropDownList' => $dataDropDownList]);
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
        $dataDropDownList = Channel::findAllChannels();
        $dataDropDownList = array_column($dataDropDownList, 'name', 'id');
        return $this->render('/channel/update', ['model' => $model, 'dataDropDownList' => $dataDropDownList]);
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
