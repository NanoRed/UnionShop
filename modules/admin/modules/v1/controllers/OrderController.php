<?php

namespace app\modules\admin\modules\v1\controllers;

use Yii;
use yii\web\Controller;
use app\modules\admin\modules\v1\models\Order;
use app\modules\admin\modules\v1\models\searchs\Order as OrderSearch;
use app\modules\admin\modules\v1\widgets\DropdownChannelListWidget;
use yii\web\NotFoundHttpException;
use app\modules\admin\modules\v1\exceptions\TableNotFoundException;

/**
 * OrderController implements the CRUD actions for Order model.
 */
class OrderController extends Controller
{
    public function init()
    {
        parent::init();

        // 设置选中的渠道，key默认为控制器类名，可自定义，具体查看小部件
        $selectedChannel = Yii::$app->request->get(DropdownChannelListWidget::PARAM_NAME);
        if (!is_null($selectedChannel)) {
            Yii::$app->session->set(static::className(), $selectedChannel);
        }
    }

    /**
     * Lists all Order models.
     * @return mixed
     * @throws \Exception
     */
    public function actionIndex()
    {
        try {
            $searchModel = new OrderSearch();
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        } catch (TableNotFoundException $e) {
            $searchModel = null;
            $dataProvider = null;
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Order model.
     * @param integer $id
     * @return mixed
     * @throws \Exception|NotFoundHttpException if the model cannot be found or something else
     */
    public function actionView($id)
    {
        try {
            return $this->render('view', [
                'model' => $this->findModel($id),
            ]);
        } catch (TableNotFoundException $e) {
            return $this->redirect(['index']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Finds the Order model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Order the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Order::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
