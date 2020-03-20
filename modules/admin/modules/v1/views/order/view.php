<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use app\modules\admin\modules\v1\models\Order;

/* @var $this yii\web\View */
/* @var $model app\modules\admin\modules\v1\models\Order */

$this->title = '订单：' . $model->order_no;
$this->params['breadcrumbs'][] = ['label' => 'Orders', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="order-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('返回列表', ['index'], ['class' => 'btn btn-primary']) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            ['label' => 'ID', 'value' => $model->id],
            ['label' => '用户ID', 'value' => $model->user_id],
            ['label' => '订单号', 'value' => $model->order_no],
            ['label' => '订单金额（元）', 'value' => $model->order_amt],
            [
                'label' => '订单状态',
                'value' => function($model) {
                    $description = '数据异常，请联系技术人员';
                    switch ($model->order_stat) {
                        case Order::REFUNDED:
                            $description = '已退款';
                            break;
                        case Order::CANCELED:
                            $description = '已取消';
                            break;
                        case Order::TO_BE_PAID:
                            $description = '等待支付';
                            break;
                        case Order::PAID:
                            $description = '已支付';
                            break;
                        case Order::COMPLETE:
                            $description = '已完成';
                            break;
                    }
                    return $description;
                },
            ],
            ['label' => '生成时间', 'value' => $model->create_time],
        ],
    ]) ?>

</div>
