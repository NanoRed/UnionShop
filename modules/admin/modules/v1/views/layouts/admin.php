<?php

use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\helpers\Html;

/* @var $this yii\web\View */

/* @var $adminController yii\web\Controller */
$adminController = $this->context;
/* @var $adminModule app\modules\admin\modules\v1\Module */
$adminModule = $adminController->module;

\app\modules\admin\modules\v1\bundles\AssetBundle::register($this);
// \yii\apidoc\templates\bootstrap\assets\AssetBundle::register($this);

// Navbar hides initial content when jumping to in-page anchor
// https://github.com/twbs/bootstrap/issues/1768
$this->registerJs(<<<JS
    var shiftWindow = function () { scrollBy(0, -50) };
    if (location.hash) setTimeout(shiftWindow, 1);
    window.addEventListener("hashchange", shiftWindow);
JS
    ,
    \yii\web\View::POS_READY
);

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <?php $this->head() ?>
    <title><?= Html::encode($adminModule->pageTitle) ?></title>
</head>
<body>

<?php $this->beginBody() ?>
<div class="wrap">
    <?php
    $navVarBegin = [
        'brandLabel' => false,
        'options' => [
            'class' => 'navbar-inverse navbar-fixed-top',
        ],
        'renderInnerContainer' => false,
        'view' => $this,
    ];
    if (!empty(Yii::$app->user->id)) {
        $navVarBegin['brandLabel'] = '主页';
        $navVarBegin['brandUrl'] = Yii::$app->homeUrl;
    }
    NavBar::begin($navVarBegin);

    foreach ($adminModule->navbar as $value) {
        $value['view'] = $this;
        echo Nav::widget($value);
    }

    NavBar::end();
    ?>
    <div class="row">
        <div class="col-md-2">
            <?php
            $adminModule->leftMenu['id'] = 'navigation';
            $adminModule->leftMenu['view'] =$this;
            echo \yii\apidoc\templates\bootstrap\SideNavWidget::widget($adminModule->leftMenu);
            ?>
        </div>
        <div class="col-md-9">
            <?php
            /* @var $content string */
            echo $content;
            ?>
        </div>
    </div>

</div>

<footer class="footer">
    <?php /* <p class="pull-right">&copy; My Company <?= date('Y') ?></p> */ ?>
    <p class="pull-left">
        <small>当前时间：<?= \app\modules\admin\modules\v1\widgets\TimeWidget::widget() ?></small>&emsp;
        <small>你的IP地址：<?= Yii::$app->request->getUserIP() ?></small>
    </p>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
