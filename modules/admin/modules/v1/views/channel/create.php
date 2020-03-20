<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model mdm\admin\models\AuthItem */
/* @var $context mdm\admin\components\ItemController */
/* @var $dataDropDownList array */

$context = $this->context;
$labels = $context->labels();
$this->title = '创建' . $labels['Item'];
$this->params['breadcrumbs'][] = ['label' => $labels['Items'], 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="auth-item-create">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>
        <?= Html::a('返回列表', ['index'], ['class' => 'btn btn-primary']) ?>
    </p>
    <br>
    <?=
    $this->render('_form', [
        'model' => $model,
        'dataDropDownList' => $dataDropDownList,
    ]);
    ?>

</div>
