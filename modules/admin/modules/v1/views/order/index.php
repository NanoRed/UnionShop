<?php

use yii\helpers\Html;
use yii\grid\GridView;
use app\modules\admin\modules\v1\models\Order;
use app\modules\admin\modules\v1\widgets\DaterangepickerWidget;
use app\modules\admin\modules\v1\widgets\DropdownChannelListWidget;

/* @var $this yii\web\View */
/* @var $searchModel app\modules\admin\modules\v1\models\searchs\Order */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $context mdm\admin\components\ItemController */
/* @var $adminModule app\modules\admin\modules\v1\Module */

$context = $this->context;
$adminModule = $context->module;
$this->title = '订单信息';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="order-index">

    <div class="row" style="display: flex;align-items: flex-end">
        <div class="col-md-3"><h1><?= Html::encode($this->title) ?></h1></div>
        <div class="col-md-3 col-md-offset-6" style="margin-bottom: 5px">
            <?= DropdownChannelListWidget::widget() ?>
        </div>
    </div>

    <?php
    if (empty($searchModel)) {
        echo Html::tag('h4', '* 请先选择渠道 *', ['style' => 'font-style: italic;']);
    } else {
        echo GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'filterPosition' => GridView::FILTER_POS_BODY,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn', 'options' => ['style' => 'width:5%']],

                ['attribute'=>'id',  'label'=>'ID', 'options' => ['style' => 'width:10%']],
                ['attribute'=>'user_id', 'label'=>'用户ID', 'options' => ['style' => 'width:10%']],
                ['attribute'=>'order_no', 'label'=>'订单号', 'options' => ['style' => 'width:30%']],
                ['attribute'=>'order_amt', 'label'=>'订单金额（元）', 'options' => ['style' => 'width:10%']],
                [
                    'attribute'=>'order_stat',
                    'label'=>'订单状态',
                    'content' => function($model) {
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
                    'options' => ['style' => 'width:10%'],
                    'filter' => Html::activeDropDownList(
                        $searchModel,
                        'order_stat',
                        [
                            '' => '全部',
                            Order::COMPLETE => '已完成',
                            Order::PAID => '已支付',
                            Order::TO_BE_PAID => '等待支付',
                            Order::CANCELED => '已取消',
                            Order::REFUNDED => '已退款',
                        ],
                        ['class' => 'form-control']
                    )
                ],
                [
                    'attribute'=>'create_time',
                    'label'=>'生成时间',
                    'options' => ['style' => 'width:20%'],
                    'filter' => DaterangepickerWidget::widget([
                        'model' => $searchModel,
                        'attribute' => 'create_time',
                        'options' => ['class' => 'form-control'],
                    ]),
                ],

                ['class' => 'yii\grid\ActionColumn', 'template' => '{view}', 'options' => ['style' => 'width:5%']],
            ],
        ]);
    }
    ?>
</div>
